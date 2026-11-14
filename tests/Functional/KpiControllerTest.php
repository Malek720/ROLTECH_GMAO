<?php

namespace App\Tests\Functional;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\Site;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class KpiControllerTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $responsable;
    private User $technicien;
    private User $admin;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedData();
        $this->entityManager->clear();
        $this->responsable = $this->entityManager->find(User::class, $this->responsable->getId());
        $this->technicien = $this->entityManager->find(User::class, $this->technicien->getId());
        $this->admin = $this->entityManager->find(User::class, $this->admin->getId());
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['notification', 'maintenance_postponement', 'intervention', 'fault', 'maintenance_schedule', 'maintenance_task', 'part', 'machine_module', 'machine', 'user', 'site', 'machine_model'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        parent::tearDown();
    }

    public function testTechnicianIsForbiddenFromKpiRoutes(): void
    {
        $this->client->loginUser($this->technicien);
        $this->client->request('GET', '/indicateurs');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTechnicianIsForbiddenFromCsvExport(): void
    {
        $this->client->loginUser($this->technicien);
        $this->client->request('GET', '/indicateurs.csv');
        self::assertResponseStatusCodeSame(403);
    }

    public function testResponsableCanOpenKpiPageAndCsv(): void
    {
        $this->client->loginUser($this->responsable);
        $this->client->request('GET', '/indicateurs');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Disponibilité par machine', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/indicateurs.csv');
        self::assertResponseIsSuccessful();
    }

    public function testAdminCanOpenKpiPage(): void
    {
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/indicateurs');
        self::assertResponseIsSuccessful();
    }

    public function testInvalidDateRangeFallsBackToTheDefaultWindow(): void
    {
        $this->client->loginUser($this->responsable);
        $this->client->request('GET', '/indicateurs?from=2026-02-31&to=2026-01-01');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('période par défaut', (string) $this->client->getResponse()->getContent());
    }

    public function testCsvUsesTheSameValidatedWindowAsTheFilename(): void
    {
        $this->client->loginUser($this->responsable);
        $this->client->request('GET', '/indicateurs.csv?from=2026-01-01&to=2026-03-31');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('indicateurs_20260331.csv', (string) $this->client->getResponse()->headers->get('Content-Disposition'));
    }

    public function testCsvHasBomSemicolonEscapingDataStatusAndMarker(): void
    {
        $this->client->loginUser($this->responsable);
        $this->client->request('GET', '/indicateurs.csv');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', (string) $this->client->getResponse()->headers->get('Content-Type'));

        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString(';', $content);
        // Data-status column header and the resolution marker.
        self::assertStringContainsString('statut_donnees', $content);
        self::assertStringContainsString('resolution_sans_horodatage', $content);
        // A site name starting with "=" is escaped with a leading single quote.
        self::assertStringContainsString("'=SITE KPI", $content);
        // The demo data status is exported so demo data is never mistaken for RolTech data.
        self::assertStringContainsString('demo', $content);
    }

    private function seedData(): void
    {
        // A formula-leading site name proves the export escaping (rule 34).
        $site = (new Site())->setName('=SITE KPI')->setCity('Sousse');
        $model = (new MachineModel())->setCode('DEMO-KPI')->setName('Modèle KPI')->setFamily('Convoyeur')->setDataStatus('demo');

        $this->responsable = (new User())->setEmail('kpi-responsable@test.local')->setFullName('Responsable KPI')->setRoles(['ROLE_RESPONSABLE'])->setSite($site)->setPassword('test');
        $this->technicien = (new User())->setEmail('kpi-technicien@test.local')->setFullName('Technicien KPI')->setRoles(['ROLE_TECHNICIEN'])->setSite($site)->setPassword('test');
        $this->admin = (new User())->setEmail('kpi-admin@test.local')->setFullName('Admin KPI')->setRoles(['ROLE_ADMIN'])->setPassword('test');

        $machine = (new Machine())->setName('Machine KPI')->setMachineModel($model)->setSerialNumber('KPI-01')->setInstallationDate(new DateTime('-2 months'))->setCategory('Convoyeur')->setStatus('en service')->setSite($site);

        // Open critical fault: counts as downtime up to the end of the window.
        $openFault = (new Fault())->setMachine($machine)->setReportedBy($this->responsable)->setReportedAt(new DateTime('-1 day'))->setDescription('Panne critique ouverte')->setSeverity('critique')->setStatus('ouverte');
        // Critical fault closed by a responsable with no finished intervention: no downtime, flagged.
        $closedFault = (new Fault())->setMachine($machine)->setReportedBy($this->responsable)->setReportedAt(new DateTime('-2 days'))->setDescription('Panne critique clôturée')->setSeverity('critique')->setStatus('resolue');
        // Curative finished intervention: feeds the mean repair time.
        $repairedFault = (new Fault())->setMachine($machine)->setReportedBy($this->responsable)->setReportedAt(new DateTime('-3 days'))->setDescription('Panne réparée')->setSeverity('moyenne')->setStatus('en cours');
        $intervention = (new Intervention())->setFault($repairedFault)->setType('curative')->setStatus('terminee')->setStartDate(new DateTime('-3 days'))->setStartedAt(new DateTime('-3 days'))->setEndDate(new DateTime('-2 days'))->setTechnician($this->technicien);

        foreach ([$site, $model, $this->responsable, $this->technicien, $this->admin, $machine, $openFault, $closedFault, $repairedFault, $intervention] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
    }
}
