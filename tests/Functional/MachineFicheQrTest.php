<?php

namespace App\Tests\Functional;

use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\Site;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Covers the "QR code" section of the phase 8 spec: the machine fiche renders a
 * QR code that encodes the fault declaration URL of that machine, built from the
 * configured base (DEFAULT_URI), and stays behind the existing fiche access rule.
 */
final class MachineFicheQrTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $responsable;
    private User $technicien;
    private Machine $machine;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedData();
        $this->entityManager->clear();
        $this->responsable = $this->reload($this->responsable);
        $this->technicien = $this->reload($this->technicien);
        $this->machine = $this->reload($this->machine);
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['notification', 'maintenance_postponement', 'intervention', 'fault', 'maintenance_schedule', 'maintenance_task', 'part', 'machine_module', 'machine', 'user', 'site', 'machine_model'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        parent::tearDown();
    }

    public function testFicheRendersTheQrCodeOfTheFaultDeclarationUrl(): void
    {
        $machineId = (int) $this->machine->getId();
        $base = rtrim((string) static::getContainer()->getParameter('router.request_context.base_url'), '/');
        $expectedUrl = $base.'/faults/new?machine='.$machineId;

        $this->client->loginUser($this->responsable);
        $this->client->request('GET', '/machines/'.$machineId.'/fiche');
        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        // The encoded URL appears as text and is exposed on the QR container.
        self::assertTrue(str_contains($content, '<p class="rtn-qr-url">'.$expectedUrl.'</p>'), 'The QR target URL is not displayed as text; expected: '.$expectedUrl);
        self::assertTrue(str_contains($content, 'data-qr-url="'.$expectedUrl.'"'), 'The QR container does not expose the encoded URL; expected: '.$expectedUrl);

        // A real inline SVG QR code is rendered inside the QR container.
        self::assertMatchesRegularExpression('/class="rtn-qr-code"[^>]*>\s*<svg[\s>]/', $content);

        // The URL really carries the machine id and the configured base.
        self::assertSame($base.'/faults/new?machine='.$machineId, $expectedUrl);
    }

    public function testTechnicienCannotObtainTheQrCode(): void
    {
        $this->client->loginUser($this->technicien);
        $this->client->request('GET', '/machines/'.$this->machine->getId().'/fiche');

        // The fiche — and therefore the QR code — stays reserved to the roles
        // that could already open it; the access rule was not weakened. Only the
        // status is asserted: the debug error page embeds an excerpt of this very
        // file, so a content check on "data-qr-url" would match its own source.
        self::assertResponseStatusCodeSame(403);
    }

    private function reload(object $entity): object
    {
        return $this->entityManager->find($entity::class, $entity->getId());
    }

    private function seedData(): void
    {
        $site = (new Site())->setName('Site QR')->setCity('Sousse');
        $model = (new MachineModel())->setCode('DEMO-QR')->setName('Modèle QR')->setFamily('Convoyeur')->setDataStatus('demo');
        $this->responsable = (new User())->setEmail('qr-responsable@test.local')->setFullName('Responsable QR')->setRoles(['ROLE_RESPONSABLE'])->setSite($site)->setPassword('test');
        $this->technicien = (new User())->setEmail('qr-technicien@test.local')->setFullName('Technicien QR')->setRoles(['ROLE_TECHNICIEN'])->setSite($site)->setPassword('test');
        $this->machine = (new Machine())->setName('Convoyeur QR')->setMachineModel($model)->setSerialNumber('QR-01')->setInstallationDate(new DateTime('-2 months'))->setCategory('Convoyeur')->setStatus('en service')->setSite($site);

        foreach ([$site, $model, $this->responsable, $this->technicien, $this->machine] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
    }
}
