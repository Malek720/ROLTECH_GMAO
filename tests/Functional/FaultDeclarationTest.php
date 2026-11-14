<?php

namespace App\Tests\Functional;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\Notification;
use App\Entity\Part;
use App\Entity\Site;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;

final class FaultDeclarationTest extends WebTestCase
{
    private AbstractBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedData();
        $this->entityManager->clear();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM notification');
        $connection->executeStatement('DELETE FROM intervention');
        $connection->executeStatement('DELETE FROM fault');
        $connection->executeStatement('DELETE FROM maintenance_schedule');
        $connection->executeStatement('DELETE FROM maintenance_task');
        $connection->executeStatement('DELETE FROM part');
        $connection->executeStatement('DELETE FROM machine_module');
        $connection->executeStatement('DELETE FROM machine');
        $connection->executeStatement('DELETE FROM machine_model');
        $connection->executeStatement('DELETE FROM user');
        $connection->executeStatement('DELETE FROM site');
        parent::tearDown();
    }

    public function testTechnicianValidSubmissionCreatesRowsAndShowsConfirmation(): void
    {
        $this->submitValidFault('karim.technicien@roltechsystems.com');
    }

    public function testResponsableValidSubmissionCreatesRowsAndShowsConfirmation(): void
    {
        $this->submitValidFault('malek.responsable@roltechsystems.com');
    }

    public function testInvalidSubmissionReturns422AndCreatesNothing(): void
    {
        $user = $this->user('karim.technicien@roltechsystems.com');
        $machine = $this->machineFor($user);
        $crawler = $this->getFaultForm($user, $machine);
        $before = $this->entityManager->getRepository(Fault::class)->count([]);

        $this->client->request('POST', '/faults/new', ['fault' => [
            '_token' => $crawler->filter('input[name="fault[_token]"]')->attr('value'),
            '_submission_token' => bin2hex(random_bytes(16)),
            'machine' => $machine->getId(),
            'symptom' => 'autre',
            'severity' => 'faible',
            'description' => '',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.rtn-field-error', 'La description est obligatoire.');
        self::assertSame($before, $this->entityManager->getRepository(Fault::class)->count([]));
    }

    public function testDoubleSubmissionTokenCreatesOneFault(): void
    {
        $user = $this->user('karim.technicien@roltechsystems.com');
        $machine = $this->machineFor($user);
        $crawler = $this->getFaultForm($user, $machine);
        $payload = ['fault' => [
            '_token' => $crawler->filter('input[name="fault[_token]"]')->attr('value'),
            '_submission_token' => bin2hex(random_bytes(16)),
            'machine' => $machine->getId(),
            'symptom' => 'autre',
            'severity' => 'faible',
            'description' => 'Une seule déclaration malgré deux clics.',
        ]];
        $before = $this->entityManager->getRepository(Fault::class)->count([]);

        $this->client->request('POST', '/faults/new', $payload);
        $this->client->request('POST', '/faults/new', $payload);

        self::assertSame($before + 1, $this->entityManager->getRepository(Fault::class)->count([]));
    }

    public function testTechnicianCannotSubmitCrossSiteMachine(): void
    {
        $user = $this->user('karim.technicien@roltechsystems.com');
        $machine = $this->machineOutsideSite($user);
        $crawler = $this->getFaultForm($user);
        $before = $this->entityManager->getRepository(Fault::class)->count([]);

        $this->client->request('POST', '/faults/new', ['fault' => [
            '_token' => $crawler->filter('input[name="fault[_token]"]')->attr('value'),
            '_submission_token' => bin2hex(random_bytes(16)),
            'machine' => $machine->getId(),
            'symptom' => 'autre',
            'severity' => 'faible',
            'description' => 'Machine d’un autre site.',
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.rtn-field-error', 'accessible');
        self::assertSame($before, $this->entityManager->getRepository(Fault::class)->count([]));
    }

    public function testAnotherUserCannotOpenFaultConfirmation(): void
    {
        $fault = $this->entityManager->getRepository(Fault::class)->findOneBy([
            'reportedBy' => $this->user('sami.technicien@roltechsystems.com'),
        ]);
        self::assertInstanceOf(Fault::class, $fault);
        $this->client->loginUser($this->user('karim.technicien@roltechsystems.com'));

        $this->client->request('GET', '/faults/'.$fault->getId().'/confirmation');

        self::assertResponseStatusCodeSame(403);
    }

    public function testFaultFormShowsOnlySelectedModelModules(): void
    {
        $user = $this->user('karim.technicien@roltechsystems.com');
        $machine = $this->machineFor($user);
        $crawler = $this->getFaultForm($user, $machine);
        $selectedModel = $machine->getMachineModel();

        self::assertSelectorExists('option[data-machine-model="'.$selectedModel->getId().'"]');
        foreach ($this->entityManager->getRepository(Machine::class)->findAll() as $otherMachine) {
            if ($otherMachine->getMachineModel() !== $selectedModel) {
                self::assertSame('false', $crawler->filter('option[data-machine-model="'.$otherMachine->getMachineModel()->getId().'"][data-model-visible]')->attr('data-model-visible'));
                self::assertSame('false', $crawler->filter('option[data-machine-model="'.$otherMachine->getMachineModel()->getId().'"][data-module]')->attr('data-model-visible'));
                break;
            }
        }
    }

    public function testToastCategoriesAreRenderedByBaseTemplate(): void
    {
        $user = $this->user('karim.technicien@roltechsystems.com');
        $this->client->loginUser($user);
        $this->client->request('GET', '/faults/new');
        $session = $this->client->getRequest()->getSession();
        $session->getFlashBag()->add('success', 'Succès de test');
        $session->getFlashBag()->add('error', 'Erreur de test');
        $session->getFlashBag()->add('warning', 'Avertissement de test');
        $session->save();

        $this->client->request('GET', '/faults/new');

        self::assertSelectorTextContains('.rtn-toast-success', 'Succès de test');
        self::assertSelectorTextContains('.rtn-toast-error', 'Erreur de test');
        self::assertSelectorTextContains('.rtn-toast-warning', 'Avertissement de test');
        self::assertSame('polite', $this->client->getCrawler()->filter('.rtn-toasts')->attr('aria-live'));
    }

    public function testSelectedSymptomIncludesCausesAndSolutionsInTroubleshootingData(): void
    {
        $user = $this->user('karim.technicien@roltechsystems.com');
        $this->client->loginUser($user);
        $response = $this->client->request('GET', '/faults/new');

        self::assertStringContainsString('motor overloaded', $this->client->getResponse()->getContent());
        self::assertStringContainsString('check conveyor overload', $this->client->getResponse()->getContent());
    }

    public function testAssignedConfirmationShowsNextSteps(): void
    {
        $fault = $this->createConfirmationFault(true);
        $this->client->loginUser($fault->getReportedBy());

        $this->client->request('GET', '/faults/'.$fault->getId().'/confirmation');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Prochaine étape');
        self::assertSelectorTextContains('body', 'Technicien affecté');
    }

    public function testUnassignedConfirmationShowsNextSteps(): void
    {
        $fault = $this->createConfirmationFault(false);
        $this->client->loginUser($fault->getReportedBy());

        $this->client->request('GET', '/faults/'.$fault->getId().'/confirmation');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Prochaine étape');
        self::assertSelectorTextContains('body', 'En attente d’affectation');
    }

    private function submitValidFault(string $email): void
    {
        $user = $this->user($email);
        $machine = $this->machineFor($user);
        $crawler = $this->getFaultForm($user, $machine);
        $before = [
            'faults' => $this->entityManager->getRepository(Fault::class)->count([]),
            'interventions' => $this->entityManager->getRepository(Intervention::class)->count([]),
            'notifications' => $this->entityManager->getRepository(Notification::class)->count([]),
        ];

        $this->client->request('POST', '/faults/new', ['fault' => [
            '_token' => $crawler->filter('input[name="fault[_token]"]')->attr('value'),
            '_submission_token' => bin2hex(random_bytes(16)),
            'machine' => $machine->getId(),
            'symptom' => 'autre',
            'severity' => 'faible',
            'description' => 'Déclaration fonctionnelle de test.',
        ]]);

        self::assertResponseRedirects();
        self::assertStringContainsString('/faults/', $this->client->getResponse()->headers->get('location'));
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Panne déclarée');
        self::assertSelectorExists('.rtn-toast-success');
        self::assertSame($before['faults'] + 1, $this->entityManager->getRepository(Fault::class)->count([]));
        self::assertSame($before['interventions'] + 1, $this->entityManager->getRepository(Intervention::class)->count([]));
        self::assertGreaterThan($before['notifications'], $this->entityManager->getRepository(\App\Entity\Notification::class)->count([]));
    }

    private function getFaultForm(User $user, ?Machine $machine = null): \Symfony\Component\DomCrawler\Crawler
    {
        $this->client->loginUser($user);
        return $this->client->request('GET', '/faults/new'.($machine ? '?machine='.$machine->getId() : ''));
    }

    private function user(string $email): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function machineFor(User $user): Machine
    {
        $machine = $this->entityManager->getRepository(Machine::class)->findOneBy(['site' => $user->getSite()]);
        self::assertInstanceOf(Machine::class, $machine);

        return $machine;
    }

    private function machineOutsideSite(User $user): Machine
    {
        foreach ($this->entityManager->getRepository(Machine::class)->findAll() as $machine) {
            if ($machine->getSite() !== $user->getSite()) {
                return $machine;
            }
        }

        self::fail('No cross-site machine exists in the test fixtures.');
    }

    private function seedData(): void
    {
        $sousse = (new Site())->setName('Sousse')->setCity('Sousse');
        $beja = (new Site())->setName('Béja')->setCity('Béja');
        $model = (new MachineModel())->setCode('TEST-OC')->setName('Test Conveyor')->setFamily('Convoyeur')->setDataStatus('demo');
        $otherModel = (new MachineModel())->setCode('TEST-HP')->setName('Test Press')->setFamily('Presse')->setDataStatus('demo');
        $module = (new MachineModule())->setCode('M1')->setName('Module test')->setPlanche('M1')->setMachineModel($model);
        $otherModule = (new MachineModule())->setCode('M1')->setName('Autre module')->setPlanche('M1')->setMachineModel($otherModel);
        $part = (new Part())->setDescription('Pièce test')->setPartNumber('DEMO-TEST-M1-01')->setModule($module);
        $otherPart = (new Part())->setDescription('Autre pièce')->setPartNumber('DEMO-TEST-HP-M1-01')->setModule($otherModule);
        $karim = (new User())->setEmail('karim.technicien@roltechsystems.com')->setFullName('Karim Trabelsi')->setRoles(['ROLE_TECHNICIEN'])->setSite($sousse)->setPassword('test');
        $sami = (new User())->setEmail('sami.technicien@roltechsystems.com')->setFullName('Sami Gharbi')->setRoles(['ROLE_TECHNICIEN'])->setSite($beja)->setPassword('test');
        $responsable = (new User())->setEmail('malek.responsable@roltechsystems.com')->setFullName('Malek Ben Sassi')->setRoles(['ROLE_RESPONSABLE'])->setSite($sousse)->setPassword('test');
        $machine = (new Machine())->setName('Machine Sousse')->setMachineModel($model)->setSerialNumber('TEST-SO')->setInstallationDate(new DateTime('2026-01-01'))->setCategory('Convoyeur')->setStatus('en service')->setSite($sousse);
        $otherMachine = (new Machine())->setName('Machine Sousse Presse')->setMachineModel($otherModel)->setSerialNumber('TEST-SO-HP')->setInstallationDate(new DateTime('2026-01-01'))->setCategory('Presse')->setStatus('en service')->setSite($sousse);
        $crossSiteMachine = (new Machine())->setName('Machine Béja')->setMachineModel($otherModel)->setSerialNumber('TEST-BJ')->setInstallationDate(new DateTime('2026-01-01'))->setCategory('Presse')->setStatus('en service')->setSite($beja);

        foreach ([$sousse, $beja, $model, $otherModel, $module, $otherModule, $part, $otherPart, $karim, $sami, $responsable, $machine, $otherMachine, $crossSiteMachine] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();

        $seedFault = (new Fault())->setMachine($crossSiteMachine)->setReportedBy($sami)->setReportedAt(new DateTime('-1 day'))->setDescription('Fault ownership test')->setSeverity('faible')->setStatus('ouverte');
        $this->entityManager->persist($seedFault);
        $this->entityManager->flush();
    }

    private function createConfirmationFault(bool $assigned): Fault
    {
        $reporter = $this->user('karim.technicien@roltechsystems.com');
        $machine = $this->machineFor($reporter);
        $fault = (new Fault())->setMachine($machine)->setReportedBy($reporter)->setReportedAt(new DateTime())->setDescription('Confirmation test')->setSeverity('faible')->setStatus('ouverte');
        $this->entityManager->persist($fault);
        if ($assigned) {
            $intervention = (new Intervention())->setFault($fault)->setType('curative')->setStartDate(new DateTime())->setStatus('planifiee')->setTechnician($reporter);
            $this->entityManager->persist($intervention);
        }
        $this->entityManager->flush();

        return $fault;
    }
}
