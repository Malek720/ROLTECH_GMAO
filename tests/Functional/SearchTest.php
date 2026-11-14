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

final class SearchTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $responsable;
    private User $technicienA;
    private User $technicienB;
    private Machine $machineA;
    private Machine $machineB;
    private Intervention $interventionA;
    private Intervention $interventionB;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        // Keep intervention ids at two digits or more so they can be searched by id,
        // given the 2-character minimum length enforced by the controller.
        $this->entityManager->getConnection()->executeStatement('ALTER TABLE intervention AUTO_INCREMENT = 1000');
        $this->seedData();
        $this->entityManager->clear();
        $this->responsable = $this->entityManager->find(User::class, $this->responsable->getId());
        $this->technicienA = $this->entityManager->find(User::class, $this->technicienA->getId());
        $this->technicienB = $this->entityManager->find(User::class, $this->technicienB->getId());
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['notification', 'maintenance_postponement', 'intervention', 'fault', 'maintenance_schedule', 'maintenance_task', 'part', 'machine_module', 'machine', 'user', 'site', 'machine_model'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        parent::tearDown();
    }

    public function testTechnicianOnlyFindsMachinesOfTheirSite(): void
    {
        $this->client->loginUser($this->technicienA);

        // Both machines match "Convoyeur", but only the technician's site must show up.
        $this->client->request('GET', '/recherche?q=Convoyeur');
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('data-machine-id="'.$this->machineA->getId().'"', $content);
        self::assertStringNotContainsString('data-machine-id="'.$this->machineB->getId().'"', $content);

        // A machine of another site returns the same empty state as no match.
        $this->client->request('GET', '/recherche?q=Beta');
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('data-machine-id="'.$this->machineB->getId().'"', $content);
        self::assertStringContainsString('Aucun résultat.', $content);
    }

    public function testTechnicianCannotFindAnotherTechniciansIntervention(): void
    {
        $idA = $this->interventionA->getId();
        $idB = $this->interventionB->getId();
        self::assertGreaterThanOrEqual(10, $idB);

        $this->client->loginUser($this->technicienA);

        // The technician finds their own intervention...
        $this->client->request('GET', '/recherche?q='.$idA);
        self::assertStringContainsString('data-intervention-id="'.$idA.'"', (string) $this->client->getResponse()->getContent());

        // ...but not the one assigned to another technician, and with no hint it exists.
        $this->client->request('GET', '/recherche?q='.$idB);
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('data-intervention-id="'.$idB.'"', $content);
        self::assertStringContainsString('Aucun résultat.', $content);
    }

    public function testResponsableFindsEverySiteAndIntervention(): void
    {
        $this->client->loginUser($this->responsable);

        $this->client->request('GET', '/recherche?q=Convoyeur');
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('data-machine-id="'.$this->machineA->getId().'"', $content);
        self::assertStringContainsString('data-machine-id="'.$this->machineB->getId().'"', $content);

        $this->client->request('GET', '/recherche?q='.$this->interventionB->getId());
        self::assertStringContainsString('data-intervention-id="'.$this->interventionB->getId().'"', (string) $this->client->getResponse()->getContent());
    }

    public function testWildcardCharactersAreTreatedLiterally(): void
    {
        $this->client->loginUser($this->responsable);

        // Two literal percent signs must not behave as "match everything".
        $this->client->request('GET', '/recherche?q='.urlencode('%%'));
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('data-machine-id="'.$this->machineA->getId().'"', $content);
        self::assertStringContainsString('Aucun résultat.', $content);

        // An underscore is literal: "SN_ALPHA" must not match the serial "SN-ALPHA-01"...
        $this->client->request('GET', '/recherche?q='.urlencode('SN_ALPHA'));
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringNotContainsString('data-machine-id="'.$this->machineA->getId().'"', $content);

        // ...whereas the real hyphen does match, proving escaping is the difference.
        $this->client->request('GET', '/recherche?q='.urlencode('SN-ALPHA'));
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('data-machine-id="'.$this->machineA->getId().'"', $content);
    }

    public function testQueryShorterThanTwoCharactersShowsHint(): void
    {
        $this->client->loginUser($this->responsable);

        $this->client->request('GET', '/recherche?q=a');
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('au moins 2 caractères', $content);
        self::assertStringNotContainsString('data-machine-id="'.$this->machineA->getId().'"', $content);
        self::assertStringNotContainsString('data-machine-id="'.$this->machineB->getId().'"', $content);
    }

    private function seedData(): void
    {
        $siteA = (new Site())->setName('Site Alpha')->setCity('Sousse');
        $siteB = (new Site())->setName('Site Beta')->setCity('Béja');
        $model = (new MachineModel())->setCode('DEMO-SEARCH')->setName('Modèle Recherche')->setFamily('Convoyeur')->setDataStatus('demo');

        $this->responsable = (new User())->setEmail('search-responsable@test.local')->setFullName('Responsable Recherche')->setRoles(['ROLE_RESPONSABLE'])->setSite($siteA)->setPassword('test');
        $this->technicienA = (new User())->setEmail('search-tech-a@test.local')->setFullName('Technicien A')->setRoles(['ROLE_TECHNICIEN'])->setSite($siteA)->setPassword('test');
        $this->technicienB = (new User())->setEmail('search-tech-b@test.local')->setFullName('Technicien B')->setRoles(['ROLE_TECHNICIEN'])->setSite($siteB)->setPassword('test');

        $this->machineA = (new Machine())->setName('Convoyeur Alpha')->setMachineModel($model)->setSerialNumber('SN-ALPHA-01')->setInstallationDate(new DateTime('-6 months'))->setCategory('Convoyeur')->setStatus('en service')->setSite($siteA);
        $this->machineB = (new Machine())->setName('Convoyeur Beta')->setMachineModel($model)->setSerialNumber('SN-BETA-99')->setInstallationDate(new DateTime('-6 months'))->setCategory('Convoyeur')->setStatus('en service')->setSite($siteB);

        $faultA = (new Fault())->setMachine($this->machineA)->setReportedBy($this->responsable)->setReportedAt(new DateTime('-2 days'))->setDescription('Panne Alpha')->setSeverity('moyenne')->setStatus('en cours');
        $faultB = (new Fault())->setMachine($this->machineB)->setReportedBy($this->responsable)->setReportedAt(new DateTime('-2 days'))->setDescription('Panne Beta')->setSeverity('moyenne')->setStatus('en cours');

        $this->interventionA = (new Intervention())->setFault($faultA)->setType('curative')->setStatus('planifiee')->setStartDate(new DateTime('-1 day'))->setTechnician($this->technicienA);
        $this->interventionB = (new Intervention())->setFault($faultB)->setType('curative')->setStatus('planifiee')->setStartDate(new DateTime('-1 day'))->setTechnician($this->technicienB);

        foreach ([$siteA, $siteB, $model, $this->responsable, $this->technicienA, $this->technicienB, $this->machineA, $this->machineB, $faultA, $faultB, $this->interventionA, $this->interventionB] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
    }
}
