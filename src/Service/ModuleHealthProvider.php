<?php

namespace App\Service;

use App\Entity\Machine;
use App\Entity\MachineModule;
use App\Entity\MaintenanceSchedule;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Computes health, open faults, next tasks and formatted tree data for machine modules.
 *
 * Rules:
 *  - Active schedules only (archivedAt IS NULL).
 *  - Worst state priority: echue > proche > ok.
 *  - Nesting propagates up: parent module health incorporates all its descendants.
 *  - Constant number of queries (batch loaded).
 */
class ModuleHealthProvider
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * @return array{
     *   machine: array{id: int, name: string, serialNumber: string},
     *   model: array{code: string, name: string, family: string, dataStatus: string, maxLoadKg: int|null},
     *   modules: list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     parentId: int|null,
     *     dataStatus: string,
     *     health: string,
     *     nextTask: array{action: string, dueAt: string}|null,
     *     openFaults: int,
     *     parts: list<array{
     *       id: int,
     *       partNumber: string|null,
     *       description: string,
     *       quantity: int|null,
     *       dataStatus: string
     *     }>
     *   }>
     * }
     */
    public function buildViewerData(Machine $machine): array
    {
        $model = $machine->getMachineModel();
        if ($model === null) {
            return [
                'machine' => [
                    'id' => (int) $machine->getId(),
                    'name' => (string) $machine->getName(),
                    'serialNumber' => (string) $machine->getSerialNumber(),
                ],
                'model' => [
                    'code' => '',
                    'name' => '',
                    'family' => '',
                    'dataStatus' => 'demo',
                    'maxLoadKg' => null,
                ],
                'modules' => [],
            ];
        }

        // 1. Constant query: modules for this model with parts eagerly fetched
        /** @var list<MachineModule> $modules */
        $modules = $this->entityManager->createQuery(
            'SELECT m, p FROM App\Entity\MachineModule m
             LEFT JOIN m.parts p
             WHERE m.machineModel = :model
             ORDER BY m.id ASC'
        )->setParameter('model', $model)->getResult();

        // 2. Constant query: active schedules for this machine with task, module and part eagerly fetched
        /** @var list<MaintenanceSchedule> $activeSchedules */
        $activeSchedules = $this->entityManager->createQuery(
            'SELECT s, t, tm, tp FROM App\Entity\MaintenanceSchedule s
             JOIN s.task t
             LEFT JOIN t.module tm
             LEFT JOIN t.part tp
             WHERE s.machine = :machine AND s.archivedAt IS NULL'
        )->setParameter('machine', $machine)->getResult();

        // 3. Constant query: open faults for this machine with module and part eagerly fetched
        $openFaults = $this->entityManager->createQuery(
            'SELECT f, fm, fp FROM App\Entity\Fault f
             LEFT JOIN f.module fm
             LEFT JOIN f.part fp
             WHERE f.machine = :machine AND f.status IN (\'ouverte\', \'en cours\')'
        )->setParameter('machine', $machine)->getResult();

        // Group schedules and open faults by module
        $directSchedulesByModule = [];
        foreach ($activeSchedules as $schedule) {
            $task = $schedule->getTask();
            $targetModuleId = $task->getModule()?->getId() ?? $task->getPart()?->getModule()?->getId();
            if ($targetModuleId !== null) {
                $directSchedulesByModule[$targetModuleId][] = $schedule;
            }
        }

        $openFaultsByModule = [];
        foreach ($openFaults as $fault) {
            $targetModuleId = $fault->getModule()?->getId() ?? $fault->getPart()?->getModule()?->getId();
            if ($targetModuleId !== null) {
                $openFaultsByModule[$targetModuleId][] = $fault;
            }
        }

        // Direct health for each module
        $directHealth = [];
        foreach ($modules as $module) {
            $moduleId = (int) $module->getId();
            $health = 'ok';
            foreach ($directSchedulesByModule[$moduleId] ?? [] as $sched) {
                $health = $this->worstHealth($health, (string) $sched->getState());
            }
            $directHealth[$moduleId] = $health;
        }

        // Parent -> Children map
        $childrenByParent = [];
        foreach ($modules as $module) {
            $parentId = $module->getParent()?->getId();
            if ($parentId !== null) {
                $childrenByParent[$parentId][] = (int) $module->getId();
            }
        }

        // Resolve propagated health (nesting propagates up)
        $resolvedHealth = [];
        foreach ($modules as $module) {
            $this->resolveModuleHealth((int) $module->getId(), $directHealth, $childrenByParent, $resolvedHealth);
        }

        // Build flat module list
        $moduleList = [];
        foreach ($modules as $module) {
            $moduleId = (int) $module->getId();
            $directScheds = $directSchedulesByModule[$moduleId] ?? [];

            // Next task is the earliest due active schedule on the module or its parts
            $nextTask = null;
            if (!empty($directScheds)) {
                $sortedScheds = $directScheds;
                usort($sortedScheds, static function (MaintenanceSchedule $a, MaintenanceSchedule $b): int {
                    $dateA = $a->getNextDueDate()?->getTimestamp() ?? PHP_INT_MAX;
                    $dateB = $b->getNextDueDate()?->getTimestamp() ?? PHP_INT_MAX;
                    return $dateA <=> $dateB;
                });
                $earliest = $sortedScheds[0];
                $nextTask = [
                    'action' => (string) $earliest->getTask()?->getAction(),
                    'dueAt' => $earliest->getNextDueDate()?->format(\DateTimeInterface::ATOM) ?? '',
                ];
            }

            // Parts list
            $partsList = [];
            foreach ($module->getParts() as $part) {
                $partsList[] = [
                    'id' => (int) $part->getId(),
                    'partNumber' => $part->getPartNumber(),
                    'description' => (string) $part->getDescription(),
                    'quantity' => $part->getQuantity(),
                    'dataStatus' => (string) $part->getDataStatus(),
                ];
            }

            $moduleList[] = [
                'id' => $moduleId,
                'code' => (string) $module->getCode(),
                'name' => (string) $module->getName(),
                'parentId' => $module->getParent()?->getId(),
                'dataStatus' => (string) $module->getDataStatus(),
                'health' => $resolvedHealth[$moduleId] ?? 'ok',
                'nextTask' => $nextTask,
                'openFaults' => count($openFaultsByModule[$moduleId] ?? []),
                'parts' => $partsList,
            ];
        }

        return [
            'machine' => [
                'id' => (int) $machine->getId(),
                'name' => (string) $machine->getName(),
                'serialNumber' => (string) $machine->getSerialNumber(),
            ],
            'model' => [
                'code' => (string) $model->getCode(),
                'name' => (string) $model->getName(),
                'family' => (string) $model->getFamily(),
                'dataStatus' => (string) $model->getDataStatus(),
                'maxLoadKg' => $model->getMaxLoadKg(),
            ],
            'modules' => $moduleList,
        ];
    }

    /**
     * Resolves health recursively so child module health propagates up to all ancestors.
     *
     * @param array<int, string> $directHealth
     * @param array<int, list<int>> $childrenByParent
     * @param array<int, string> $resolvedHealth
     */
    private function resolveModuleHealth(
        int $moduleId,
        array $directHealth,
        array $childrenByParent,
        array &$resolvedHealth,
    ): string {
        if (isset($resolvedHealth[$moduleId])) {
            return $resolvedHealth[$moduleId];
        }

        $health = $directHealth[$moduleId] ?? 'ok';
        foreach ($childrenByParent[$moduleId] ?? [] as $childId) {
            $childHealth = $this->resolveModuleHealth($childId, $directHealth, $childrenByParent, $resolvedHealth);
            $health = $this->worstHealth($health, $childHealth);
        }

        $resolvedHealth[$moduleId] = $health;

        return $health;
    }

    /**
     * Returns worst health state: echue > proche > ok.
     */
    public function worstHealth(string $current, string $candidate): string
    {
        $priority = ['echue' => 3, 'proche' => 2, 'ok' => 1];
        $pCurrent = $priority[$current] ?? 1;
        $pCandidate = $priority[$candidate] ?? 1;

        return $pCandidate > $pCurrent ? $candidate : $current;
    }
}
