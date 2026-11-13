<?php

namespace App\Tests\Unit;

use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\MaintenanceSchedule;
use App\Entity\MaintenanceTask;
use App\Repository\MaintenanceScheduleRepository;
use App\Repository\MaintenanceTaskRepository;
use App\Service\MachineScheduleGenerator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MachineScheduleGeneratorTest extends TestCase
{
    public function testGeneratesSchedulesForTheCurrentModel(): void
    {
        $model = $this->model('HLS-CA1100');
        $machine = $this->machine($model);
        $task = (new MaintenanceTask())->setMachineModel($model)->setAction('contrôle');
        $tasks = $this->createMock(MaintenanceTaskRepository::class);
        $tasks->expects(self::once())->method('findForModel')->with($model)->willReturn([$task]);
        $schedules = $this->createMock(MaintenanceScheduleRepository::class);
        $schedules->expects(self::once())->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(MaintenanceSchedule::class));

        $result = (new MachineScheduleGenerator($tasks, $schedules, $entityManager))->synchronize($machine);

        self::assertSame(['archived' => 0, 'created' => 1], $result);
    }

    public function testModelChangeArchivesOldSchedulesAndKeepsHistory(): void
    {
        $oldModel = $this->model('OC-CA1100');
        $newModel = $this->model('HP-2000X');
        $machine = $this->machine($newModel);
        $oldTask = (new MaintenanceTask())->setMachineModel($oldModel)->setAction('ancienne tâche');
        $oldSchedule = (new MaintenanceSchedule())->setMachine($machine)->setTask($oldTask);
        $machine->getMaintenanceSchedules()->add($oldSchedule);
        $newTask = (new MaintenanceTask())->setMachineModel($newModel)->setAction('nouvelle tâche');
        $tasks = $this->createMock(MaintenanceTaskRepository::class);
        $tasks->method('findForModel')->with($newModel)->willReturn([$newTask]);
        $schedules = $this->createMock(MaintenanceScheduleRepository::class);
        $schedules->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');

        $result = (new MachineScheduleGenerator($tasks, $schedules, $entityManager))->synchronize($machine, $oldModel);

        self::assertSame(1, $result['archived']);
        self::assertNotNull($oldSchedule->getArchivedAt());
        self::assertSame(1, $result['created']);
    }

    public function testDoesNotDuplicateAnExistingActiveSchedule(): void
    {
        $model = $this->model('AirMax-300');
        $machine = $this->machine($model);
        $task = (new MaintenanceTask())->setMachineModel($model)->setAction('contrôle');
        $existing = (new MaintenanceSchedule())->setMachine($machine)->setTask($task);
        $tasks = $this->createMock(MaintenanceTaskRepository::class);
        $tasks->method('findForModel')->willReturn([$task]);
        $schedules = $this->createMock(MaintenanceScheduleRepository::class);
        $schedules->method('findOneBy')->willReturn($existing);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $result = (new MachineScheduleGenerator($tasks, $schedules, $entityManager))->synchronize($machine);

        self::assertSame(0, $result['created']);
    }

    private function model(string $code): MachineModel
    {
        return (new MachineModel())->setCode($code)->setName($code)->setFamily('Demo');
    }

    private function machine(MachineModel $model): Machine
    {
        return (new Machine())->setName('Machine test')->setMachineModel($model)
            ->setSerialNumber('TEST')->setInstallationDate(new \DateTime('2026-01-01'))
            ->setCategory('Demo')->setStatus('en service');
    }
}
