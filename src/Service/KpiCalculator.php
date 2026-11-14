<?php

namespace App\Service;

/**
 * Pure KPI calculator (phase 8). No repository, no EntityManager: it takes plain arrays
 * describing machines, faults and interventions so it can be unit tested without a database.
 * {@see \App\Service\AdminStatsProvider} loads the data in a constant number of queries and
 * feeds it to this class.
 *
 * Window: default = the last 12 months through today; the user may pick a range
 * (invalid dates fall back to the default, the range is capped at 5 years). Months are
 * grouped in PHP with the Africa/Tunis timezone, never with database date functions.
 *
 * Availability per machine (machines run 24h/day):
 *   denominator = window end - max(window start, machine installationDate);
 *   if that duration is <= 0 the machine shows "n/d" (never divide by zero).
 *   Downtime only comes from `critique` faults: for each such fault the interval is
 *   [reportedAt, resolution) clipped to the window, where resolution = the latest `endDate`
 *   among that fault's `terminee` interventions. A fault still `ouverte` or `en cours` runs to
 *   the end of the window. A fault `resolue` without any finished intervention (closed by a
 *   responsable) adds no downtime and is counted as `resolution_sans_horodatage`.
 *   Intervals whose resolution is before reportedAt are ignored. Overlapping intervals are
 *   merged (union) per machine before summing, and availability is clamped to 0-100 %.
 *   Known limitations: `moyenne` / `faible` faults and planned maintenance (`en maintenance`
 *   status, preventive interventions) are NOT downtime.
 *
 * Mean repair time: curative interventions only, status `terminee`, `endDate` inside the
 *   window; duration = endDate - startedAt, or endDate - startDate when startedAt is null.
 *   Open interventions are excluded.
 *
 * Faults per site per month: faults whose reportedAt is inside the window, grouped by site and
 *   month; also groupable by model. Model rows carry the model dataStatus so demo data is marked.
 */
final class KpiCalculator
{
    public const TIMEZONE = 'Africa/Tunis';
    public const DEFAULT_MONTHS = 12;
    public const MAX_YEARS = 5;

    /**
     * @return array{from:\DateTimeImmutable,to:\DateTimeImmutable,isDefault:bool}
     */
    public function resolveWindow(?string $from, ?string $to, ?\DateTimeImmutable $now = null): array
    {
        $timezone = new \DateTimeZone(self::TIMEZONE);
        $now ??= new \DateTimeImmutable('now', $timezone);

        $defaultTo = $now->setTime(23, 59, 59);
        $defaultFrom = $now->modify(sprintf('-%d months', self::DEFAULT_MONTHS))->setTime(0, 0, 0);

        $fromDate = $this->parseDay($from, $timezone);
        $toDate = $this->parseDay($to, $timezone);

        if ($fromDate === null || $toDate === null || $toDate < $fromDate) {
            return ['from' => $defaultFrom, 'to' => $defaultTo, 'isDefault' => true];
        }

        $start = $fromDate->setTime(0, 0, 0);
        $end = $toDate->setTime(23, 59, 59);
        $earliest = $end->modify(sprintf('-%d years', self::MAX_YEARS));
        if ($start < $earliest) {
            $start = $earliest;
        }

        return ['from' => $start, 'to' => $end, 'isDefault' => false];
    }

    /**
     * Availability per machine, merging overlapping downtime intervals.
     *
     * @param list<array{id:int,name?:string,siteId?:?int,siteName?:?string,modelCode?:?string,dataStatus?:string,installationDate?:?\DateTimeImmutable}> $machines
     * @param list<array{id?:int,machineId:int,severity:string,status:string,reportedAt:?\DateTimeImmutable}> $faults
     * @param list<array{faultId?:?int,status:string,type?:string,endDate?:?\DateTimeImmutable}> $interventions
     *
     * @return list<array<string,mixed>>
     */
    public function availabilityPerMachine(
        array $machines,
        array $faults,
        array $interventions,
        \DateTimeImmutable $windowStart,
        \DateTimeImmutable $windowEnd,
    ): array {
        $windowStartTs = $windowStart->getTimestamp();
        $windowEndTs = $windowEnd->getTimestamp();

        $latestEndByFault = $this->latestFinishedEndByFault($interventions);

        $intervalsByMachine = [];
        $openByMachine = [];
        $noTimestampByMachine = [];

        foreach ($faults as $fault) {
            if (($fault['severity'] ?? null) !== 'critique') {
                continue;
            }

            $machineId = $fault['machineId'] ?? null;
            $reportedAt = $fault['reportedAt'] ?? null;
            if ($machineId === null || !$reportedAt instanceof \DateTimeImmutable) {
                continue;
            }

            $status = $fault['status'] ?? null;
            $faultId = $fault['id'] ?? null;

            if ($faultId !== null && isset($latestEndByFault[$faultId])) {
                $resolutionTs = $latestEndByFault[$faultId];
            } elseif ($status === 'ouverte' || $status === 'en cours') {
                $resolutionTs = $windowEndTs;
                $openByMachine[$machineId] = ($openByMachine[$machineId] ?? 0) + 1;
            } elseif ($status === 'resolue') {
                // Closed by a responsable with no finished intervention: no downtime.
                $noTimestampByMachine[$machineId] = ($noTimestampByMachine[$machineId] ?? 0) + 1;
                continue;
            } else {
                continue;
            }

            $reportedTs = $reportedAt->getTimestamp();
            if ($resolutionTs < $reportedTs) {
                continue; // resolution before the report: ignore the interval
            }

            $start = max($reportedTs, $windowStartTs);
            $end = min($resolutionTs, $windowEndTs);
            if ($end <= $start) {
                continue; // interval does not overlap the window
            }

            $intervalsByMachine[$machineId][] = [$start, $end];
        }

        $rows = [];
        foreach ($machines as $machine) {
            $machineId = $machine['id'];

            $effectiveStartTs = $windowStartTs;
            $installation = $machine['installationDate'] ?? null;
            if ($installation instanceof \DateTimeImmutable) {
                $effectiveStartTs = max($windowStartTs, $installation->getTimestamp());
            }

            $windowHours = ($windowEndTs - $effectiveStartTs) / 3600;
            $windowHours = max(0.0, $windowHours);

            $downtimeSeconds = 0;
            foreach ($this->mergeIntervals($intervalsByMachine[$machineId] ?? []) as [$start, $end]) {
                $downtimeSeconds += $end - $start;
            }
            $downtimeHours = $downtimeSeconds / 3600;

            $availability = $windowHours > 0
                ? max(0.0, min(100.0, ($windowHours - $downtimeHours) / $windowHours * 100))
                : null;

            $rows[$machineId] = [
                'machineId' => $machineId,
                'machineName' => $machine['name'] ?? '',
                'siteId' => $machine['siteId'] ?? null,
                'siteName' => $machine['siteName'] ?? null,
                'modelCode' => $machine['modelCode'] ?? null,
                'dataStatus' => $machine['dataStatus'] ?? 'demo',
                'windowHours' => round($windowHours, 4),
                'downtimeHours' => round($downtimeHours, 4),
                'availability' => $availability === null ? null : round($availability, 2),
                'openCritiqueFaults' => $openByMachine[$machineId] ?? 0,
                'faultsWithoutResolutionTimestamp' => $noTimestampByMachine[$machineId] ?? 0,
            ];
        }

        return array_values($rows);
    }

    /**
     * @param list<array{type?:?string,status?:?string,startDate?:?\DateTimeImmutable,startedAt?:?\DateTimeImmutable,endDate?:?\DateTimeImmutable}> $interventions
     *
     * @return array{count:int,totalHours:float,meanHours:?float}
     */
    public function meanRepairTime(
        array $interventions,
        \DateTimeImmutable $windowStart,
        \DateTimeImmutable $windowEnd,
    ): array {
        $fromTs = $windowStart->getTimestamp();
        $toTs = $windowEnd->getTimestamp();
        $count = 0;
        $totalSeconds = 0;

        foreach ($interventions as $intervention) {
            if (($intervention['type'] ?? null) !== 'curative') {
                continue;
            }
            if (($intervention['status'] ?? null) !== 'terminee') {
                continue;
            }

            $end = $intervention['endDate'] ?? null;
            if (!$end instanceof \DateTimeImmutable) {
                continue;
            }
            $endTs = $end->getTimestamp();
            if ($endTs < $fromTs || $endTs > $toTs) {
                continue;
            }

            $start = $intervention['startedAt'] ?? null;
            if (!$start instanceof \DateTimeImmutable) {
                $start = $intervention['startDate'] ?? null;
            }
            if (!$start instanceof \DateTimeImmutable) {
                continue;
            }

            $count++;
            $totalSeconds += max(0, $endTs - $start->getTimestamp());
        }

        $totalHours = $totalSeconds / 3600;

        return [
            'count' => $count,
            'totalHours' => round($totalHours, 4),
            'meanHours' => $count > 0 ? round($totalHours / $count, 2) : null,
        ];
    }

    /**
     * @param list<array{machineId:int,reportedAt:?\DateTimeImmutable}> $faults
     * @param list<array{id:int,siteId?:?int,siteName?:?string,modelCode?:?string,dataStatus?:string}> $machines
     *
     * @return list<array{group:string,groupType:string,month:string,count:int,dataStatus:?string}>
     */
    public function faultsPerSitePerMonth(
        array $faults,
        array $machines,
        \DateTimeImmutable $windowStart,
        \DateTimeImmutable $windowEnd,
        string $groupBy = 'site',
    ): array {
        $machinesById = [];
        foreach ($machines as $machine) {
            $machinesById[$machine['id']] = $machine;
        }

        $timezone = new \DateTimeZone(self::TIMEZONE);
        $fromTs = $windowStart->getTimestamp();
        $toTs = $windowEnd->getTimestamp();
        $byModel = $groupBy === 'model';

        $counts = [];
        foreach ($faults as $fault) {
            $reportedAt = $fault['reportedAt'] ?? null;
            if (!$reportedAt instanceof \DateTimeImmutable) {
                continue;
            }
            $reportedTs = $reportedAt->getTimestamp();
            if ($reportedTs < $fromTs || $reportedTs > $toTs) {
                continue;
            }

            $machine = $machinesById[$fault['machineId'] ?? null] ?? null;
            if ($machine === null) {
                continue;
            }

            $month = $reportedAt->setTimezone($timezone)->format('Y-m');
            $group = $byModel ? (string) ($machine['modelCode'] ?? '') : (string) ($machine['siteName'] ?? '');
            $key = $group.'|'.$month;

            if (!isset($counts[$key])) {
                $counts[$key] = [
                    'group' => $group,
                    'groupType' => $byModel ? 'model' : 'site',
                    'month' => $month,
                    'count' => 0,
                    'dataStatus' => $byModel ? ($machine['dataStatus'] ?? null) : null,
                ];
            }
            $counts[$key]['count']++;
        }

        ksort($counts);

        return array_values($counts);
    }

    /**
     * Convenience entry point that runs every KPI at once.
     *
     * @param list<array<string,mixed>> $machines
     * @param list<array<string,mixed>> $faults
     * @param list<array<string,mixed>> $interventions
     *
     * @return array{availability:list<array<string,mixed>>,meanRepairTime:array{count:int,totalHours:float,meanHours:?float},faultsPerSite:list<array<string,mixed>>,faultsPerModel:list<array<string,mixed>>}
     */
    public function compute(
        array $machines,
        array $faults,
        array $interventions,
        \DateTimeImmutable $windowStart,
        \DateTimeImmutable $windowEnd,
    ): array {
        return [
            'availability' => $this->availabilityPerMachine($machines, $faults, $interventions, $windowStart, $windowEnd),
            'meanRepairTime' => $this->meanRepairTime($interventions, $windowStart, $windowEnd),
            'faultsPerSite' => $this->faultsPerSitePerMonth($faults, $machines, $windowStart, $windowEnd, 'site'),
            'faultsPerModel' => $this->faultsPerSitePerMonth($faults, $machines, $windowStart, $windowEnd, 'model'),
        ];
    }

    /**
     * @param list<array{faultId?:?int,status:string,endDate?:?\DateTimeImmutable}> $interventions
     *
     * @return array<int,int> fault id => latest finished end timestamp
     */
    private function latestFinishedEndByFault(array $interventions): array
    {
        $latest = [];
        foreach ($interventions as $intervention) {
            if (($intervention['status'] ?? null) !== 'terminee') {
                continue;
            }
            $end = $intervention['endDate'] ?? null;
            $faultId = $intervention['faultId'] ?? null;
            if ($faultId === null || !$end instanceof \DateTimeImmutable) {
                continue;
            }
            $timestamp = $end->getTimestamp();
            if (!isset($latest[$faultId]) || $timestamp > $latest[$faultId]) {
                $latest[$faultId] = $timestamp;
            }
        }

        return $latest;
    }

    /**
     * @param list<array{0:int,1:int}> $intervals
     *
     * @return list<array{0:int,1:int}>
     */
    private function mergeIntervals(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }

        usort($intervals, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        $merged = [];
        [$start, $end] = $intervals[0];
        foreach ($intervals as [$intervalStart, $intervalEnd]) {
            if ($intervalStart <= $end) {
                if ($intervalEnd > $end) {
                    $end = $intervalEnd;
                }
            } else {
                $merged[] = [$start, $end];
                $start = $intervalStart;
                $end = $intervalEnd;
            }
        }
        $merged[] = [$start, $end];

        return $merged;
    }

    private function parseDay(?string $value, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, $timezone);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null; // rejects rolled-over dates such as 2026-02-31
        }

        return $date;
    }
}
