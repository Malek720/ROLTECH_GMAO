<?php

namespace App\Tests\Functional;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\InterventionPart;
use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\MaintenanceSchedule;
use App\Entity\MaintenanceTask;
use App\Entity\Notification;
use App\Entity\Part;
use App\Entity\Site;
use App\Entity\User;
use App\Service\InterventionWorkflow;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InterventionWorkflowTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $technician;
    private User $otherTechnician;
    private User $responsable;
    private Machine $machine;
    private Part $part;
    private MaintenanceSchedule $schedule;
    private Fault $fault;
    private Intervention $preventive;
    private Intervention $curative;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedData();
        $this->entityManager->clear();
        $this->reloadEntities();
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM intervention_part');
        $connection->executeStatement('DELETE FROM notification');
        $connection->executeStatement('DELETE FROM intervention');
        $connection->executeStatement('DELETE FROM fault');
        $connection->executeStatement('DELETE FROM maintenance_schedule');
        $connection->executeStatement('DELETE FROM maintenance_task');
        $connection->executeStatement('DELETE FROM part');
        $connection->executeStatement('DELETE FROM machine_module');
        $connection->executeStatement('DELETE FROM machine');
        $connection->executeStatement('DELETE FROM user');
        $connection->executeStatement('DELETE FROM site');
        $connection->executeStatement('DELETE FROM machine_model');
        parent::tearDown();
    }

    public function testStartRequiresEveryLockoutItem(): void
    {
        $this->loginAsTechnician();
        $this->postStart(['electrical', 'pneumatic']);

        self::assertResponseRedirects();
        self::assertSame('planifiee', $this->reload($this->preventive)->getStatus());
    }

    public function testStartRecordsLockoutAndStartedAt(): void
    {
        $this->loginAsTechnician();
        $this->postStart(array_keys(InterventionWorkflow::LOCKOUT_ITEMS));

        $intervention = $this->reload($this->preventive);
        self::assertSame('en cours', $intervention->getStatus());
        self::assertNotNull($intervention->getStartedAt());
        self::assertNotNull($intervention->getLockoutConfirmedAt());
    }

    public function testStartingTwiceDoesNotChangeAnInProgressIntervention(): void
    {
        $this->loginAsTechnician();
        $this->postStart(array_keys(InterventionWorkflow::LOCKOUT_ITEMS));
        $this->preventive = $this->reload($this->preventive);
        $startedAt = $this->preventive->getStartedAt();
        $technician = $this->entityManager->find(User::class, $this->technician->getId());
        $this->expectException(\InvalidArgumentException::class);
        static::getContainer()->get(InterventionWorkflow::class)->start(
            $this->preventive,
            $technician,
            array_keys(InterventionWorkflow::LOCKOUT_ITEMS)
        );

        $intervention = $this->reload($this->preventive);
        self::assertSame('en cours', $intervention->getStatus());
        self::assertEquals($startedAt, $intervention->getStartedAt());
    }

    public function testPlanifieeInterventionCannotBeCompleted(): void
    {
        $this->preventive = $this->reload($this->preventive);
        $technician = $this->entityManager->find(User::class, $this->technician->getId());
        $this->expectException(\InvalidArgumentException::class);
        static::getContainer()->get(InterventionWorkflow::class)->complete(
            $this->preventive,
            $technician,
            'Trop tôt'
        );
    }

    public function testCannotInterveneRequiresAReason(): void
    {
        $this->loginAsTechnician();
        $this->client->request('POST', '/interventions/'.$this->preventive->getId().'/cannot-intervene', [
            '_token' => $this->csrf('intervention_cannot_'.$this->preventive->getId()),
            'reason' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame($this->technician->getId(), $this->reload($this->preventive)->getTechnician()->getId());
    }

    public function testProcedureProgressIsPersistedAndRequiredForCompletion(): void
    {
        $this->loginAsTechnician();
        $this->postStart(array_keys(InterventionWorkflow::LOCKOUT_ITEMS));
        $this->client->request('POST', '/interventions/'.$this->preventive->getId().'/checklist', [
            '_token' => $this->csrf('intervention_checklist_'.$this->preventive->getId()),
            'steps' => ['0'],
        ]);

        $progress = $this->reload($this->preventive)->getChecklistProgress();
        self::assertTrue($progress['0']);
        self::assertFalse($progress['1']);

        $this->client->request('POST', '/interventions/'.$this->preventive->getId().'/complete', [
            '_token' => $this->csrf('intervention_complete_'.$this->preventive->getId()),
            'comment' => 'Contrôle partiel',
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testCompletionUpdatesPreventiveScheduleAndSavesPart(): void
    {
        $this->loginAsTechnician();
        $this->postStart(array_keys(InterventionWorkflow::LOCKOUT_ITEMS));
        $this->client->request('POST', '/interventions/'.$this->preventive->getId().'/checklist', [
            '_token' => $this->csrf('intervention_checklist_'.$this->preventive->getId()),
            'steps' => ['0', '1'],
        ]);
        $this->client->request('POST', '/interventions/'.$this->preventive->getId().'/complete', [
            '_token' => $this->csrf('intervention_complete_'.$this->preventive->getId()),
            'comment' => 'Intervention terminée',
            'parts' => [$this->part->getId() => '2'],
        ]);

        self::assertResponseRedirects();
        $schedule = $this->reload($this->schedule);
        self::assertSame('ok', $schedule->getState());
        self::assertSame('terminee', $this->reload($this->preventive)->getStatus());
        $line = $this->entityManager->getRepository(InterventionPart::class)->findOneBy(['intervention' => $this->preventive]);
        self::assertInstanceOf(InterventionPart::class, $line);
        self::assertSame(2, $line->getQuantity());
    }

    public function testCurativeCompletionResolvesFaultAndNotifiesReporterAndResponsable(): void
    {
        $this->client->loginUser($this->otherTechnician);
        $this->postStartFor($this->curative);
        $this->client->request('POST', '/interventions/'.$this->curative->getId().'/complete', [
            '_token' => $this->csrf('intervention_complete_'.$this->curative->getId()),
            'comment' => 'Panne réparée',
        ]);

        self::assertResponseRedirects();
        self::assertSame('resolue', $this->reload($this->fault)->getStatus());
        self::assertSame(2, $this->entityManager->getRepository(Notification::class)->count([]));
    }

    public function testCannotInterveneUnassignsAndNotifiesResponsableWithoutChangingStatus(): void
    {
        $this->loginAsTechnician();
        $this->client->request('POST', '/interventions/'.$this->preventive->getId().'/cannot-intervene', [
            '_token' => $this->csrf('intervention_cannot_'.$this->preventive->getId()),
            'reason' => 'Je ne suis pas habilité sur cette machine.',
        ]);

        $intervention = $this->reload($this->preventive);
        self::assertNull($intervention->getTechnician());
        self::assertSame('planifiee', $intervention->getStatus());
        self::assertSame(1, $this->entityManager->getRepository(Notification::class)->count([]));
    }

    public function testTechnicianCannotOpenAnotherTechniciansIntervention(): void
    {
        $this->client->loginUser($this->technician);
        $this->client->request('GET', '/interventions/'.$this->curative->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testCsrfIsRejectedForStart(): void
    {
        $this->loginAsTechnician();
        $this->client->request('POST', '/interventions/'.$this->preventive->getId().'/start', [
            '_token' => 'invalid',
            'lockout' => array_keys(InterventionWorkflow::LOCKOUT_ITEMS),
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    private function postStart(array $items): void
    {
        $this->postStartFor($this->preventive, $items);
    }

    private function postStartFor(Intervention $intervention, ?array $items = null): void
    {
        $this->client->request('POST', '/interventions/'.$intervention->getId().'/start', [
            '_token' => $this->csrf('intervention_start_'.$intervention->getId()),
            'lockout' => $items ?? array_keys(InterventionWorkflow::LOCKOUT_ITEMS),
        ]);
    }

    private function loginAsTechnician(): void
    {
        $this->client->loginUser($this->technician);
    }

    private function csrf(string $id): string
    {
        preg_match('/_(\d+)$/', $id, $matches);
        $interventionId = $matches[1] ?? $this->preventive->getId();
        $crawler = $this->client->request('GET', '/interventions/'.$interventionId);
        $action = match (true) {
            str_starts_with($id, 'intervention_start_') => 'start',
            str_starts_with($id, 'intervention_checklist_') => 'checklist',
            str_starts_with($id, 'intervention_complete_') => 'complete',
            str_starts_with($id, 'intervention_cannot_') => 'cannot-intervene',
            default => throw new \LogicException('Unknown test CSRF token.'),
        };

        $tokenField = $crawler->filter('form[action$="/'.$interventionId.'/'.$action.'"] input[name="_token"]');
        if ($tokenField->count() === 0) {
            return static::getContainer()->get('security.csrf.token_manager')->getToken($id)->getValue();
        }

        return $tokenField->attr('value');
    }

    private function reload(object $entity): object
    {
        $this->entityManager->clear();

        return $this->entityManager->find($entity::class, $entity->getId());
    }

    private function reloadEntities(): void
    {
        $this->technician = $this->reload($this->technician);
        $this->otherTechnician = $this->reload($this->otherTechnician);
        $this->responsable = $this->reload($this->responsable);
        $this->machine = $this->reload($this->machine);
        $this->part = $this->reload($this->part);
        $this->schedule = $this->reload($this->schedule);
        $this->fault = $this->reload($this->fault);
        $this->preventive = $this->reload($this->preventive);
        $this->curative = $this->reload($this->curative);
    }

    private function seedData(): void
    {
        $site = (new Site())->setName('Sousse')->setCity('Sousse');
        $model = (new MachineModel())->setCode('TEST-INT')->setName('Modèle intervention')->setFamily('Convoyeur')->setDataStatus('demo');
        $module = (new MachineModule())->setCode('M1')->setName('Module test')->setPlanche('M1')->setMachineModel($model);
        $this->part = (new Part())->setDescription('Pièce test')->setPartNumber('DEMO-TEST-INT-M1-01')->setModule($module);
        $task = (new MaintenanceTask())->setMachineModel($model)->setModule($module)->setAction('Vérifier la tension')->setFrequencyDays(30)->setFrequencyHours(720)->setProcedureChecklist(json_encode(['Mesurer la tension', 'Comparer à la valeur de référence']));
        $this->technician = (new User())->setEmail('tech-a@test.local')->setFullName('Technicien A')->setRoles(['ROLE_TECHNICIEN'])->setSite($site)->setPassword('test');
        $this->otherTechnician = (new User())->setEmail('tech-b@test.local')->setFullName('Technicien B')->setRoles(['ROLE_TECHNICIEN'])->setSite($site)->setPassword('test');
        $this->responsable = (new User())->setEmail('resp@test.local')->setFullName('Responsable')->setRoles(['ROLE_RESPONSABLE'])->setSite($site)->setPassword('test');
        $this->machine = (new Machine())->setName('Machine test')->setMachineModel($model)->setSerialNumber('INT-01')->setInstallationDate(new DateTime('-1 month'))->setCategory('Convoyeur')->setStatus('en service')->setSite($site);
        $this->schedule = (new MaintenanceSchedule())->initializeFromMachine($this->machine, $task);
        $this->fault = (new Fault())->setMachine($this->machine)->setReportedBy($this->responsable)->setReportedAt(new DateTime())->setDescription('Panne de test')->setSeverity('faible')->setStatus('ouverte');
        $this->preventive = (new Intervention())->setSchedule($this->schedule)->setType('preventive')->setStartDate(new DateTime())->setStatus('planifiee')->setTechnician($this->technician);
        $this->curative = (new Intervention())->setFault($this->fault)->setType('curative')->setStartDate(new DateTime())->setStatus('planifiee')->setTechnician($this->otherTechnician);

        foreach ([$site, $model, $module, $this->part, $task, $this->technician, $this->otherTechnician, $this->responsable, $this->machine, $this->schedule, $this->fault, $this->preventive, $this->curative] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
    }
}
