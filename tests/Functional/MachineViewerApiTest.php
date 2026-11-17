<?php

namespace App\Tests\Functional;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\MaintenanceSchedule;
use App\Entity\MaintenanceTask;
use App\Entity\Part;
use App\Entity\Site;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional tests for Phase 7.2:
 * - GET /parc/{id}/viewer-data (role scoping, JSON shape)
 * - /faults/new?machine=<id>&module=<id>&part=<id> (prefill validation)
 * - /machines/{id}/fiche?module=<id> (scoped timeline filter)
 * - /machines/{id}/fiche access control (still 403 for technicien)
 *
 * Runs against roltech_gmao_copy_test, repeatable twice in a row.
 */
final class MachineViewerApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private Site $siteSousse;
    private Site $siteBeja;
    private User $admin;
    private User $responsable;
    private User $techSousse;
    private MachineModel $modelA;
    private MachineModel $modelB;
    private Machine $machineSousse;
    private Machine $machineBeja;
    private MachineModule $moduleA1;
    private MachineModule $moduleA2;
    private MachineModule $moduleA3;
    private MachineModule $moduleB1;
    private Part $partA1;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedData();
        $this->entityManager->clear();
        $this->siteSousse = $this->reload($this->siteSousse);
        $this->siteBeja = $this->reload($this->siteBeja);
        $this->admin = $this->reload($this->admin);
        $this->responsable = $this->reload($this->responsable);
        $this->techSousse = $this->reload($this->techSousse);
        $this->machineSousse = $this->reload($this->machineSousse);
        $this->machineBeja = $this->reload($this->machineBeja);
        $this->moduleA1 = $this->reload($this->moduleA1);
        $this->moduleA2 = $this->reload($this->moduleA2);
        $this->moduleA3 = $this->reload($this->moduleA3);
        $this->moduleB1 = $this->reload($this->moduleB1);
        $this->partA1 = $this->reload($this->partA1);
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['notification', 'maintenance_postponement', 'intervention', 'fault', 'maintenance_schedule', 'maintenance_task', 'part', 'machine_module', 'machine', 'user', 'site', 'machine_model'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        parent::tearDown();
    }

    public function testViewerDataEndpointRoleScoping(): void
    {
        // 1. Technicien from Sousse requests Sousse machine -> 200 OK
        $this->client->loginUser($this->techSousse);
        $this->client->request('GET', '/parc/'.$this->machineSousse->getId().'/viewer-data');
        self::assertResponseStatusCodeSame(200);

        // Verify JSON keys structure
        $json = json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('machine', $json);
        self::assertArrayHasKey('model', $json);
        self::assertArrayHasKey('modules', $json);

        self::assertSame(['id', 'name', 'serialNumber'], array_keys($json['machine']));
        self::assertSame(['code', 'name', 'family', 'dataStatus', 'maxLoadKg'], array_keys($json['model']));

        self::assertNotEmpty($json['modules']);
        $mod = $json['modules'][0];
        self::assertArrayHasKey('id', $mod);
        self::assertArrayHasKey('code', $mod);
        self::assertArrayHasKey('name', $mod);
        self::assertArrayHasKey('parentId', $mod);
        self::assertArrayHasKey('dataStatus', $mod);
        self::assertArrayHasKey('health', $mod);
        self::assertArrayHasKey('nextTask', $mod);
        self::assertArrayHasKey('openFaults', $mod);
        self::assertArrayHasKey('parts', $mod);

        $part = $mod['parts'][0];
        self::assertSame(['id', 'partNumber', 'description', 'quantity', 'dataStatus'], array_keys($part));

        // 2. Technicien from Sousse requests Béja machine -> 403 Forbidden
        $this->client->request('GET', '/parc/'.$this->machineBeja->getId().'/viewer-data');
        self::assertResponseStatusCodeSame(403);

        // 3. Responsable requests Béja machine -> 200 OK
        $this->client->loginUser($this->responsable);
        $this->client->request('GET', '/parc/'.$this->machineBeja->getId().'/viewer-data');
        self::assertResponseStatusCodeSame(200);

        // 4. Admin requests Sousse machine -> 200 OK
        $this->client->loginUser($this->admin);
        $this->client->request('GET', '/parc/'.$this->machineSousse->getId().'/viewer-data');
        self::assertResponseStatusCodeSame(200);
    }

    public function testFaultDeclarationPrefillValidation(): void
    {
        $this->client->loginUser($this->techSousse);

        // Case 1: Valid machine and valid module belonging to machine's model
        $crawler = $this->client->request('GET', '/faults/new?machine='.$this->machineSousse->getId().'&module='.$this->moduleA1->getId());
        self::assertResponseStatusCodeSame(200);
        // Form selects the module
        $selectedOption = $crawler->filter('select[name="fault[module]"] option[selected]');
        self::assertGreaterThanOrEqual(1, $selectedOption->count());
        self::assertSame((string) $this->moduleA1->getId(), $selectedOption->attr('value'));

        // Case 2: Valid machine but module from another model (Module B1 belongs to Model B)
        $this->client->request('GET', '/faults/new?machine='.$this->machineSousse->getId().'&module='.$this->moduleB1->getId());
        self::assertResponseStatusCodeSame(200);
        // Warning flash shown
        self::assertSelectorExists('.rtn-toast-warning');
        self::assertSelectorTextContains('.rtn-toast-warning', 'Le module demandé n’appartient pas au modèle de cette machine');

        // Case 3: Invisible machine for techSousse (Machine Béja)
        $this->client->request('GET', '/faults/new?machine='.$this->machineBeja->getId().'&module='.$this->moduleB1->getId());
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorExists('.rtn-toast-warning');
        self::assertSelectorTextContains('.rtn-toast-warning', 'La machine demandée n’est pas accessible');
    }

    public function testMachineFicheTimelineFilterAndRoleAccess(): void
    {
        // 1. Technicien opening machine fiche -> 403 Forbidden
        $this->client->loginUser($this->techSousse);
        $this->client->request('GET', '/machines/'.$this->machineSousse->getId().'/fiche');
        self::assertResponseStatusCodeSame(403);

        // 2. Responsable opening machine fiche without filter -> sees all timeline items (Module A1 + Module A2)
        $this->client->loginUser($this->responsable);
        $crawler = $this->client->request('GET', '/machines/'.$this->machineSousse->getId().'/fiche');
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('ol.rtn-timeline', 'Panne M1');
        self::assertSelectorTextContains('ol.rtn-timeline', 'Panne M2');

        // 3. Responsable with ?module=<moduleA1->id> -> timeline scoped to Module A1 only
        $crawlerScoped = $this->client->request('GET', '/machines/'.$this->machineSousse->getId().'/fiche?module='.$this->moduleA1->getId());
        self::assertResponseStatusCodeSame(200);
        self::assertStringContainsString('Chronologie — Module : Renvoi 180', $crawlerScoped->text());
        self::assertSelectorTextContains('ol.rtn-timeline', 'Panne M1');
        // Panne M2 is on Module A2 and must NOT appear
        $timelineText = $crawlerScoped->filter('ol.rtn-timeline')->text();
        self::assertStringNotContainsString('Panne M2', $timelineText);

        // 4. Invalid module (from model B) is ignored, full timeline rendered
        $crawlerInvalid = $this->client->request('GET', '/machines/'.$this->machineSousse->getId().'/fiche?module='.$this->moduleB1->getId());
        self::assertResponseStatusCodeSame(200);
        self::assertSelectorTextContains('ol.rtn-timeline', 'Panne M1');
        self::assertSelectorTextContains('ol.rtn-timeline', 'Panne M2');
    }

    public function testHealthPropagationUpTwoNestingLevelsAndArchivedIgnored(): void
    {
        /** @var \App\Service\ModuleHealthProvider $healthProvider */
        $healthProvider = static::getContainer()->get(\App\Service\ModuleHealthProvider::class);

        $data = $healthProvider->buildViewerData($this->machineSousse);
        $modulesByCode = [];
        foreach ($data['modules'] as $module) {
            $modulesByCode[$module['code']] = $module;
        }

        // M3 (leaf) has direct active schedule 'echue'
        self::assertSame('echue', $modulesByCode['M3']['health']);
        // M2 has archived schedule 'echue' (which must be ignored), but propagates 'echue' from child M3 (level 1)
        self::assertSame('echue', $modulesByCode['M2']['health']);
        // M1 has active schedule 'ok', but propagates 'echue' from grandchild M3 via M2 (level 2)
        self::assertSame('echue', $modulesByCode['M1']['health']);

        // Next task on M3 is 'Remplacement galet M3'
        self::assertNotNull($modulesByCode['M3']['nextTask']);
        self::assertSame('Remplacement galet M3', $modulesByCode['M3']['nextTask']['action']);
    }

    public function testMachineFicheRendersExactlyOneViewerWithDataUrl(): void
    {
        $this->client->loginUser($this->responsable);
        $crawler = $this->client->request('GET', '/machines/'.$this->machineSousse->getId().'/fiche');
        self::assertResponseIsSuccessful();

        $viewerElements = $crawler->filter('[data-rtn-viewer]');
        self::assertCount(1, $viewerElements, 'Fiche machine must contain exactly one [data-rtn-viewer] element.');

        $dataUrl = $viewerElements->attr('data-url');
        self::assertNotNull($dataUrl, '[data-rtn-viewer] must carry data-url.');
        self::assertStringEndsWith('/parc/'.$this->machineSousse->getId().'/viewer-data', $dataUrl);
    }

    public function testFaultFormWithMachineParamRendersViewerDataUrl(): void
    {
        $this->client->loginUser($this->techSousse);
        $crawler = $this->client->request('GET', '/faults/new?machine='.$this->machineSousse->getId());
        self::assertResponseIsSuccessful();

        $viewerElements = $crawler->filter('[data-rtn-viewer]');
        self::assertCount(1, $viewerElements, 'Fault form must contain exactly one [data-rtn-viewer] element.');

        $dataUrl = $viewerElements->attr('data-url');
        self::assertNotNull($dataUrl, '[data-rtn-viewer] must carry data-url when machine is prefilled.');
        self::assertStringEndsWith('/parc/'.$this->machineSousse->getId().'/viewer-data', $dataUrl);
    }

    public function testFaultFormWithoutMachineParamRendersValidOrNoDataUrlAndScopesTechnicien(): void
    {
        $this->client->loginUser($this->techSousse);
        $crawler = $this->client->request('GET', '/faults/new');
        self::assertResponseIsSuccessful();

        $viewerElements = $crawler->filter('[data-rtn-viewer]');
        self::assertCount(1, $viewerElements, 'Fault form must contain exactly one [data-rtn-viewer] element.');

        $dataUrl = $viewerElements->attr('data-url');
        // Contract: either no data-url attribute at all, or a valid URL ending in /parc/<id>/viewer-data, never empty string ""
        if ($dataUrl !== null) {
            self::assertNotSame('', $dataUrl, 'data-url must never be empty string.');
            self::assertMatchesRegularExpression('#/parc/\d+/viewer-data$#', $dataUrl);
        }

        // Technicien scoping: option elements with data-viewer-url must only belong to Sousse site
        $optionViewerUrls = $crawler->filter('select[name="fault[machine]"] option[data-viewer-url]')->each(
            fn ($node) => $node->attr('data-viewer-url')
        );

        self::assertNotEmpty($optionViewerUrls);
        foreach ($optionViewerUrls as $url) {
            self::assertStringEndsWith('/parc/'.$this->machineSousse->getId().'/viewer-data', $url);
            self::assertStringNotContainsString('/parc/'.$this->machineBeja->getId().'/viewer-data', $url);
        }
    }

    private function seedData(): void
    {
        $this->siteSousse = (new Site())->setName('Site Sousse')->setCity('Sousse')->setLatitude(35.82)->setLongitude(10.60);
        $this->siteBeja = (new Site())->setName('Site Béja')->setCity('Béja')->setLatitude(36.72)->setLongitude(9.18);
        $this->entityManager->persist($this->siteSousse);
        $this->entityManager->persist($this->siteBeja);

        $this->admin = (new User())->setEmail('admin_test@roltech.test')->setFullName('Admin')->setRoles(['ROLE_ADMIN'])->setPassword('hashed');
        $this->responsable = (new User())->setEmail('resp_test@roltech.test')->setFullName('Resp')->setRoles(['ROLE_RESPONSABLE'])->setPassword('hashed')->setSite($this->siteSousse);
        $this->techSousse = (new User())->setEmail('tech_sousse@roltech.test')->setFullName('Tech Sousse')->setRoles(['ROLE_TECHNICIEN'])->setPassword('hashed')->setSite($this->siteSousse);
        $this->entityManager->persist($this->admin);
        $this->entityManager->persist($this->responsable);
        $this->entityManager->persist($this->techSousse);

        $this->modelA = (new MachineModel())->setCode('MODEL-A')->setName('Modèle A')->setFamily('Convoyeur')->setDataStatus('confirmed')->setMaxLoadKg(20);
        $this->modelB = (new MachineModel())->setCode('MODEL-B')->setName('Modèle B')->setFamily('Presse')->setDataStatus('demo');
        $this->entityManager->persist($this->modelA);
        $this->entityManager->persist($this->modelB);

        $this->moduleA1 = (new MachineModule())->setCode('M1')->setName('Renvoi 180')->setPlanche('P1')->setMachineModel($this->modelA)->setDataStatus('confirmed');
        $this->moduleA2 = (new MachineModule())->setCode('M2')->setName('Poulie')->setPlanche('P2')->setParent($this->moduleA1)->setMachineModel($this->modelA)->setDataStatus('confirmed');
        $this->moduleA3 = (new MachineModule())->setCode('M3')->setName('Galet')->setPlanche('P3')->setParent($this->moduleA2)->setMachineModel($this->modelA)->setDataStatus('confirmed');
        $this->moduleB1 = (new MachineModule())->setCode('MB1')->setName('Hydraulique')->setPlanche('PB1')->setMachineModel($this->modelB)->setDataStatus('demo');
        $this->entityManager->persist($this->moduleA1);
        $this->entityManager->persist($this->moduleA2);
        $this->entityManager->persist($this->moduleA3);
        $this->entityManager->persist($this->moduleB1);

        $this->partA1 = (new Part())->setPartNumber('PA-01')->setDescription('Roulement')->setQuantity(2)->setModule($this->moduleA1)->setDataStatus('confirmed');
        $this->entityManager->persist($this->partA1);

        $this->machineSousse = (new Machine())
            ->setName('Convoyeur Sousse')
            ->setSerialNumber('SN-SOUSSE-1')
            ->setInstallationDate(new DateTime('-100 days'))
            ->setCategory('Convoyeur')
            ->setMachineModel($this->modelA)
            ->setSite($this->siteSousse)
            ->setStatus('en service');

        $this->machineBeja = (new Machine())
            ->setName('Presse Béja')
            ->setSerialNumber('SN-BEJA-1')
            ->setInstallationDate(new DateTime('-100 days'))
            ->setCategory('Presse')
            ->setMachineModel($this->modelB)
            ->setSite($this->siteBeja)
            ->setStatus('en service');
        $this->entityManager->persist($this->machineSousse);
        $this->entityManager->persist($this->machineBeja);

        $taskA1 = (new MaintenanceTask())->setAction('Graissage M1')->setModule($this->moduleA1)->setMachineModel($this->modelA);
        $taskA2 = (new MaintenanceTask())->setAction('Contrôle M2')->setModule($this->moduleA2)->setMachineModel($this->modelA);
        $taskA3 = (new MaintenanceTask())->setAction('Remplacement galet M3')->setModule($this->moduleA3)->setMachineModel($this->modelA);
        $this->entityManager->persist($taskA1);
        $this->entityManager->persist($taskA2);
        $this->entityManager->persist($taskA3);

        // Schedule on M1: ok
        $schedA1 = (new MaintenanceSchedule())->initializeFromMachine($this->machineSousse, $taskA1, new DateTime('-10 days'))->setNextDueDate(new DateTime('+10 days'))->setState('ok');
        $this->entityManager->persist($schedA1);

        // Schedule on M2: echue but ARCHIVED -> must be ignored!
        $schedA2 = (new MaintenanceSchedule())->initializeFromMachine($this->machineSousse, $taskA2, new DateTime('-20 days'))->setNextDueDate(new DateTime('-2 days'))->setState('echue')->setArchivedAt(new DateTime('-1 days'));
        $this->entityManager->persist($schedA2);

        // Schedule on M3 (grandchild of M1): active with state 'echue' -> propagates up to M2 and M1!
        $schedA3 = (new MaintenanceSchedule())->initializeFromMachine($this->machineSousse, $taskA3, new DateTime('-15 days'))->setNextDueDate(new DateTime('+3 days'))->setState('echue');
        $this->entityManager->persist($schedA3);

        // Fault on M1
        $fault1 = (new Fault())->setMachine($this->machineSousse)->setModule($this->moduleA1)->setReportedBy($this->admin)->setReportedAt(new DateTime('-1 days'))->setDescription('Panne M1')->setStatus('ouverte')->setSeverity('faible');
        $this->entityManager->persist($fault1);

        // Fault on M2
        $fault2 = (new Fault())->setMachine($this->machineSousse)->setModule($this->moduleA2)->setReportedBy($this->admin)->setReportedAt(new DateTime('-1 days'))->setDescription('Panne M2')->setStatus('ouverte')->setSeverity('moyenne');
        $this->entityManager->persist($fault2);

        $this->entityManager->flush();
    }

    /**
     * @template T of object
     * @param T $entity
     * @return T
     */
    private function reload(object $entity): object
    {
        /** @var class-string<T> $class */
        $class = get_class($entity);
        $id = $entity->getId();
        $reloaded = $this->entityManager->find($class, $id);
        self::assertNotNull($reloaded);

        return $reloaded;
    }
}
