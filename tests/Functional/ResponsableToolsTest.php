<?php

namespace App\Tests\Functional;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\MaintenanceSchedule;
use App\Entity\MaintenanceTask;
use App\Entity\Notification;
use App\Entity\Part;
use App\Entity\Site;
use App\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ResponsableToolsTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private User $responsable;
    private User $technician;
    private User $otherTechnician;
    private Machine $machine;
    private Machine $otherMachine;
    private MaintenanceSchedule $schedule;
    private Intervention $intervention;
    private Fault $fault;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->seedData();
        $this->entityManager->clear();
        $this->responsable = $this->reload($this->responsable);
        $this->technician = $this->reload($this->technician);
        $this->otherTechnician = $this->reload($this->otherTechnician);
        $this->machine = $this->reload($this->machine);
        $this->schedule = $this->reload($this->schedule);
        $this->intervention = $this->reload($this->intervention);
        $this->fault = $this->reload($this->fault);
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['notification', 'maintenance_postponement', 'intervention', 'fault', 'maintenance_schedule', 'maintenance_task', 'part', 'machine_module', 'machine', 'user', 'site', 'machine_model'] as $table) {
            $connection->executeStatement('DELETE FROM '.$table);
        }
        parent::tearDown();
    }

    public function testAssignmentAndReassignmentNotifyTheTechnician(): void
    {
        $this->login();
        $this->client->request('POST', '/responsable/dispatch/'.$this->intervention->getId().'/assign', [
            '_token' => $this->token('responsable_assign_'.$this->intervention->getId()),
            'technician' => $this->technician->getId(),
        ]);
        self::assertResponseRedirects('/responsable/dispatch');
        self::assertSame($this->technician->getId(), $this->reload($this->intervention)->getTechnician()->getId());
        self::assertSame(1, $this->entityManager->getRepository(Notification::class)->count(['recipient' => $this->technician]));

        $this->client->request('POST', '/responsable/dispatch/'.$this->intervention->getId().'/assign', [
            '_token' => $this->token('responsable_assign_'.$this->intervention->getId()),
            'technician' => $this->otherTechnician->getId(),
        ]);
        self::assertSame($this->technician->getId(), $this->reload($this->intervention)->getTechnician()->getId());
    }

    public function testManualInterventionValidationRejectsMissingCurativeDetails(): void
    {
        $this->login();
        $this->client->request('POST', '/responsable/machines/'.$this->machine->getId().'/interventions/new', [
            'intervention' => [
                '_token' => $this->token('responsable_manual_intervention_'.$this->machine->getId()),
                'type' => 'curative',
                'description' => '',
                'severity' => '',
            ],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(1, $this->entityManager->getRepository(Intervention::class)->count([]));
    }

    public function testSerialNumberUniquenessAndTechnicianAccessAreEnforced(): void
    {
        $this->login();
        $this->client->request('POST', '/machines/'.$this->machine->getId().'/fiche', [
            '_token' => $this->token('machine_edit_'.$this->machine->getId()),
            'serial_number' => 'RESP-02',
            'status' => 'en service',
        ]);
        self::assertResponseStatusCodeSame(422);

        $this->client->loginUser($this->technician);
        $this->client->request('GET', '/machines/'.$this->machine->getId().'/fiche');
        self::assertResponseStatusCodeSame(403);
    }

    public function testCloseAndReopenRequireTheReopenReason(): void
    {
        $this->login();
        $this->client->request('POST', '/responsable/faults/'.$this->fault->getId().'/close', [
            '_token' => $this->token('responsable_fault_close_'.$this->fault->getId()),
        ]);
        self::assertSame('resolue', $this->reload($this->fault)->getStatus());
        $this->client->request('POST', '/responsable/faults/'.$this->fault->getId().'/reopen', [
            '_token' => $this->token('responsable_fault_reopen_'.$this->fault->getId()),
            'reason' => '',
        ]);
        self::assertSame('resolue', $this->reload($this->fault)->getStatus());
    }

    public function testPostponementIsLoggedAndUpdatesTheSchedule(): void
    {
        $this->login();
        $newDate = (clone $this->schedule->getNextDueDate())->modify('+7 days')->format('Y-m-d');
        $this->client->request('POST', '/responsable/schedules/'.$this->schedule->getId().'/postpone', [
            '_token' => $this->token('responsable_schedule_postpone_'.$this->schedule->getId()),
            'new_date' => $newDate,
            'reason' => 'Arrêt de production programmé.',
        ]);
        self::assertResponseRedirects();
        self::assertSame($newDate, $this->reload($this->schedule)->getNextDueDate()->format('Y-m-d'));
        self::assertSame(1, $this->entityManager->getRepository('App\Entity\MaintenancePostponement')->count([]));
    }

    public function testAssignmentRejectsInvalidCsrf(): void
    {
        $this->login();
        $this->client->request('POST', '/responsable/dispatch/'.$this->intervention->getId().'/assign', [
            '_token' => 'invalid',
            'technician' => $this->technician->getId(),
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    private function login(): void
    {
        $this->client->loginUser($this->responsable);
        $this->client->request('GET', '/responsable');
    }

    private function token(string $id): string
    {
        preg_match('/_(\d+)$/', $id, $matches);
        $entityId = $matches[1] ?? '';
        if (str_starts_with($id, 'responsable_assign_')) {
            $url = '/responsable/dispatch';
            $actionPart = '/'.$entityId.'/assign';
        } elseif (str_starts_with($id, 'responsable_manual_intervention_')) {
            $url = '/responsable/machines/'.$entityId.'/interventions/new';
            $actionPart = null;
        } elseif (str_starts_with($id, 'responsable_fault_close_')) {
            $url = '/machines/'.$this->machine->getId().'/fiche';
            $actionPart = '/faults/'.$entityId.'/close';
        } elseif (str_starts_with($id, 'responsable_fault_reopen_')) {
            $url = '/machines/'.$this->machine->getId().'/fiche';
            $actionPart = '/faults/'.$entityId.'/reopen';
        } elseif (str_starts_with($id, 'responsable_schedule_postpone_')) {
            $url = '/machines/'.$this->machine->getId().'/fiche';
            $actionPart = '/schedules/'.$entityId.'/postpone';
        } else {
            $url = '/machines/'.$entityId.'/fiche';
            $actionPart = null;
        }
        $crawler = $this->client->request('GET', $url);
        $tokens = $crawler->filter('input[name*="_token"]');
        if ($actionPart !== null) {
            $tokens = $crawler->filter('form')->reduce(static function ($form) use ($actionPart): bool {
                return str_contains((string) $form->attr('action'), $actionPart);
            })->filter('input[name*="_token"]');
        }
        if ($tokens->count() === 0) {
            throw new \LogicException('The expected CSRF form is missing (HTTP '.$this->client->getResponse()->getStatusCode().'): '.substr($this->client->getResponse()->getContent(), 0, 300));
        }

        return $tokens->first()->attr('value');
    }

    private function reload(object $entity): object
    {
        return $this->entityManager->find($entity::class, $entity->getId());
    }

    private function seedData(): void
    {
        $site = (new Site())->setName('Sousse')->setCity('Sousse');
        $model = (new MachineModel())->setCode('TEST-RESP')->setName('Modèle responsable')->setFamily('Convoyeur')->setDataStatus('demo');
        $module = (new MachineModule())->setCode('M1')->setName('Module')->setPlanche('M1')->setMachineModel($model);
        $part = (new Part())->setDescription('Pièce')->setPartNumber('DEMO-TEST-RESP-M1-01')->setModule($module);
        $task = (new MaintenanceTask())->setMachineModel($model)->setModule($module)->setAction('Contrôler')->setFrequencyDays(30)->setFrequencyHours(720);
        $this->responsable = (new User())->setEmail('responsable@test.local')->setFullName('Responsable')->setRoles(['ROLE_RESPONSABLE'])->setSite($site)->setPassword('test');
        $this->technician = (new User())->setEmail('technician@test.local')->setFullName('Technicien')->setRoles(['ROLE_TECHNICIEN'])->setSite($site)->setPassword('test');
        $this->otherTechnician = (new User())->setEmail('technician2@test.local')->setFullName('Technicien 2')->setRoles(['ROLE_TECHNICIEN'])->setSite($site)->setPassword('test');
        $this->machine = (new Machine())->setName('Machine')->setMachineModel($model)->setSerialNumber('RESP-01')->setInstallationDate(new DateTime('-1 month'))->setCategory('Convoyeur')->setStatus('en service')->setSite($site);
        $this->otherMachine = (new Machine())->setName('Autre machine')->setMachineModel($model)->setSerialNumber('RESP-02')->setInstallationDate(new DateTime('-1 month'))->setCategory('Convoyeur')->setStatus('en service')->setSite($site);
        $this->schedule = (new MaintenanceSchedule())->initializeFromMachine($this->machine, $task)->setState('echue');
        $this->fault = (new Fault())->setMachine($this->machine)->setReportedBy($this->responsable)->setReportedAt(new DateTime())->setDescription('Panne')->setSeverity('faible')->setStatus('ouverte');
        $this->intervention = (new Intervention())->setSchedule($this->schedule)->setType('preventive')->setStartDate(new DateTime())->setStatus('planifiee');
        foreach ([$site, $model, $module, $part, $task, $this->responsable, $this->technician, $this->otherTechnician, $this->machine, $this->otherMachine, $this->schedule, $this->fault, $this->intervention] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
    }
}
