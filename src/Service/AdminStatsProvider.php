<?php

namespace App\Service;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\Machine;
use App\Repository\FaultRepository;
use App\Repository\InterventionRepository;
use App\Repository\MachineRepository;
use App\Repository\UserRepository;
use App\Repository\MaintenanceScheduleRepository;
use App\Repository\SiteRepository;

class AdminStatsProvider
{
    public function __construct(
        private MachineRepository $machineRepository,
        private FaultRepository $faultRepository,
        private InterventionRepository $interventionRepository,
        private UserRepository $userRepository,
        private MaintenanceScheduleRepository $maintenanceScheduleRepository,
        private SiteRepository $siteRepository,
        private KpiCalculator $kpiCalculator,
    ) {
    }

    public function getStats(): array
    {
        return [
            'machines' => $this->machineStats(),
            'faults' => $this->faultStats(),
            'interventions' => $this->interventionStats(),
            'users' => $this->userStats(),
            'maintenance' => $this->maintenanceStats(),
            'sites' => ['total' => $this->siteRepository->count([])],
        ];
    }

    /**
     * KPI report (phase 8). Loads machines, faults and finished interventions in three queries
     * (no query per machine) and leaves every formula to {@see KpiCalculator}.
     *
     * @return array{window:array{from:string,to:string,isDefault:bool},availability:list<array<string,mixed>>,meanRepairTime:array{count:int,totalHours:float,meanHours:?float},faultsPerSite:list<array<string,mixed>>,faultsPerModel:list<array<string,mixed>>}
     */
    public function getKpiReport(?string $from = null, ?string $to = null): array
    {
        $window = $this->kpiCalculator->resolveWindow($from, $to);

        $machines = $this->mapMachinesForKpi($this->machineRepository->findAllWithSiteAndModel());
        $faults = $this->mapFaultsForKpi($this->faultRepository->findReportedUpTo($window['to']));
        $interventions = $this->mapInterventionsForKpi($this->interventionRepository->findFinishedUpTo($window['to']));

        $kpis = $this->kpiCalculator->compute($machines, $faults, $interventions, $window['from'], $window['to']);

        return array_merge([
            'window' => [
                'from' => $window['from']->format('Y-m-d'),
                'to' => $window['to']->format('Y-m-d'),
                'isDefault' => $window['isDefault'],
            ],
        ], $kpis);
    }

    /**
     * @param list<Machine> $machines
     *
     * @return list<array<string,mixed>>
     */
    private function mapMachinesForKpi(array $machines): array
    {
        $rows = [];
        foreach ($machines as $machine) {
            $site = $machine->getSite();
            $model = $machine->getMachineModel();
            $rows[] = [
                'id' => $machine->getId(),
                'name' => $machine->getName() ?? '',
                'siteId' => $site?->getId(),
                'siteName' => $site?->getName(),
                'modelCode' => $model?->getCode(),
                'dataStatus' => $model?->getDataStatus() ?? 'demo',
                'installationDate' => $this->toImmutable($machine->getInstallationDate()),
            ];
        }

        return $rows;
    }

    /**
     * @param list<Fault> $faults
     *
     * @return list<array<string,mixed>>
     */
    private function mapFaultsForKpi(array $faults): array
    {
        $rows = [];
        foreach ($faults as $fault) {
            $rows[] = [
                'id' => $fault->getId(),
                'machineId' => $fault->getMachine()?->getId(),
                'severity' => $fault->getSeverity() ?? '',
                'status' => $fault->getStatus() ?? '',
                'reportedAt' => $this->toImmutable($fault->getReportedAt()),
            ];
        }

        return $rows;
    }

    /**
     * @param list<Intervention> $interventions
     *
     * @return list<array<string,mixed>>
     */
    private function mapInterventionsForKpi(array $interventions): array
    {
        $rows = [];
        foreach ($interventions as $intervention) {
            $rows[] = [
                'id' => $intervention->getId(),
                'faultId' => $intervention->getFault()?->getId(),
                'type' => $intervention->getType() ?? '',
                'status' => $intervention->getStatus() ?? '',
                'startDate' => $this->toImmutable($intervention->getStartDate()),
                'startedAt' => $this->toImmutable($intervention->getStartedAt()),
                'endDate' => $this->toImmutable($intervention->getEndDate()),
            ];
        }

        return $rows;
    }

    private function toImmutable(?\DateTimeInterface $date): ?\DateTimeImmutable
    {
        return $date === null ? null : \DateTimeImmutable::createFromInterface($date);
    }

    private function maintenanceStats(): array
    {
        $schedules = array_values(array_filter(
            $this->maintenanceScheduleRepository->findAll(),
            static fn ($schedule): bool => $schedule->getArchivedAt() === null
        ));

        return [
            'byState' => $this->countBy($schedules, fn ($schedule) => $schedule->getState()),
        ];
    }

    private function machineStats(): array
    {
        $machines = $this->machineRepository->findAll();

        return [
            'byStatus' => $this->countBy($machines, fn($m) => $m->getStatus()),
            'byCategory' => $this->countBy($machines, fn($m) => $m->getCategory()),
        ];
    }

    private function faultStats(): array
    {
        $faults = $this->faultRepository->findAll();

        return [
            'bySeverity' => $this->countBy($faults, fn($f) => $f->getSeverity()),
            'byStatus' => $this->countBy($faults, fn($f) => $f->getStatus()),
            'overTime' => $this->countByMonth($faults, fn($f) => $f->getReportedAt()),
        ];
    }

    private function interventionStats(): array
    {
        $interventions = $this->interventionRepository->findAll();

        return [
            'byType' => $this->countBy($interventions, fn($i) => $i->getType()),
            'byStatus' => $this->countBy($interventions, fn($i) => $i->getStatus()),
            'overTime' => $this->countByMonth($interventions, fn($i) => $i->getStartDate()),
        ];
    }

    private function userStats(): array
    {
        $users = $this->userRepository->findAll();
        $counts = [];

        foreach ($users as $user) {
            foreach ($user->getRoles() as $role) {
                $counts[$role] = ($counts[$role] ?? 0) + 1;
            }
        }

        return ['byRole' => $counts];
    }

    private function countBy(array $items, callable $getKey): array
    {
        $counts = [];
        foreach ($items as $item) {
            $key = $getKey($item) ?? 'Non défini';
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        return $counts;
    }

    private function countByMonth(array $items, callable $getDate): array
    {
        $counts = [];
        foreach ($items as $item) {
            $date = $getDate($item);
            if (!$date) {
                continue;
            }
            $key = $date->format('Y-m');
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
        ksort($counts);

        // re-key with readable French month labels for direct chart use
        $labeled = [];
        foreach ($counts as $ym => $count) {
            $d = \DateTime::createFromFormat('Y-m-d', $ym . '-01');
            $labeled[$d->format('M Y')] = $count;
        }
        return $labeled;
    }
}