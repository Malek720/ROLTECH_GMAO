<?php

namespace App\Service;

use App\Repository\FaultRepository;
use App\Repository\InterventionRepository;
use App\Repository\MachineRepository;
use App\Repository\UserRepository;

class AdminStatsProvider
{
    public function __construct(
        private MachineRepository $machineRepository,
        private FaultRepository $faultRepository,
        private InterventionRepository $interventionRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function getStats(): array
    {
        return [
            'machines' => $this->machineStats(),
            'faults' => $this->faultStats(),
            'interventions' => $this->interventionStats(),
            'users' => $this->userStats(),
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