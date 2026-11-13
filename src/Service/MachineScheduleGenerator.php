<?php

namespace App\Service;

use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\MaintenanceSchedule;
use App\Repository\MaintenanceScheduleRepository;
use App\Repository\MaintenanceTaskRepository;
use Doctrine\ORM\EntityManagerInterface;

final class MachineScheduleGenerator
{
    public function __construct(
        private MaintenanceTaskRepository $taskRepository,
        private MaintenanceScheduleRepository $scheduleRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Archives schedules whose task belongs to another model and creates missing
     * schedules for the machine's current model. Existing history is retained.
     *
     * @return array{archived: int, created: int}
     */
    public function synchronize(Machine $machine, ?MachineModel $previousModel = null): array
    {
        $currentModel = $machine->getMachineModel();
        if ($currentModel === null) {
            return ['archived' => 0, 'created' => 0];
        }

        $archived = 0;
        foreach ($machine->getMaintenanceSchedules() as $schedule) {
            if ($schedule->getArchivedAt() !== null) {
                continue;
            }
            if ($schedule->getTask()?->getMachineModel() !== $currentModel) {
                $schedule->setArchivedAt(new \DateTime());
                $archived++;
            }
        }

        $created = 0;
        foreach ($this->taskRepository->findForModel($currentModel) as $task) {
            if ($this->scheduleRepository->findOneBy([
                'machine' => $machine,
                'task' => $task,
                'archivedAt' => null,
            ]) !== null) {
                continue;
            }

            $schedule = (new MaintenanceSchedule())->initializeFromMachine($machine, $task);
            $this->entityManager->persist($schedule);
            $created++;
        }

        return ['archived' => $archived, 'created' => $created];
    }
}
