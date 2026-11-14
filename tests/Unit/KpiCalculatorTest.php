<?php

namespace App\Tests\Unit;

use App\Service\KpiCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Requirement: KPI formulas (phase 8 "KPI formulas" section). Pure unit tests: plain arrays in,
 * no database. Window used by most tests: 2026-01-01 00:00:00 -> 2026-01-11 00:00:00 = 240 h.
 */
final class KpiCalculatorTest extends TestCase
{
    private KpiCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new KpiCalculator();
    }

    private function tz(): \DateTimeZone
    {
        return new \DateTimeZone(KpiCalculator::TIMEZONE);
    }

    private function at(string $dateTime, ?\DateTimeZone $timezone = null): \DateTimeImmutable
    {
        return new \DateTimeImmutable($dateTime, $timezone ?? $this->tz());
    }

    /** @return array{0:\DateTimeImmutable,1:\DateTimeImmutable} */
    private function window(): array
    {
        return [$this->at('2026-01-01 00:00:00'), $this->at('2026-01-11 00:00:00')];
    }

    /** @return array<string,mixed> */
    private function machine(
        int $id,
        ?string $installedAt = null,
        string $siteName = 'Sousse',
        string $modelCode = 'OC-CA1100',
        string $dataStatus = 'confirmed',
    ): array {
        return [
            'id' => $id,
            'name' => 'Machine '.$id,
            'siteId' => 1,
            'siteName' => $siteName,
            'modelCode' => $modelCode,
            'dataStatus' => $dataStatus,
            'installationDate' => $installedAt === null ? null : $this->at($installedAt),
        ];
    }

    /** @return array<string,mixed> */
    private function fault(int $id, int $machineId, string $reportedAt, string $severity = 'critique', string $status = 'ouverte'): array
    {
        return [
            'id' => $id,
            'machineId' => $machineId,
            'severity' => $severity,
            'status' => $status,
            'reportedAt' => $this->at($reportedAt),
        ];
    }

    /** @return array<string,mixed> */
    private function intervention(?int $faultId, string $type, string $status, ?string $startDate, ?string $startedAt, ?string $endDate): array
    {
        return [
            'faultId' => $faultId,
            'type' => $type,
            'status' => $status,
            'startDate' => $startDate === null ? null : $this->at($startDate),
            'startedAt' => $startedAt === null ? null : $this->at($startedAt),
            'endDate' => $endDate === null ? null : $this->at($endDate),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     *
     * @return array<int,array<string,mixed>>
     */
    private function byMachineId(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row['machineId']] = $row;
        }

        return $byId;
    }

    public function testWindowDefaultsToLastTwelveMonthsThroughToday(): void
    {
        $now = $this->at('2026-06-15 10:00:00');
        $window = $this->calculator->resolveWindow(null, null, $now);

        self::assertTrue($window['isDefault']);
        self::assertSame('2025-06-15 00:00:00', $window['from']->format('Y-m-d H:i:s'));
        self::assertSame('2026-06-15 23:59:59', $window['to']->format('Y-m-d H:i:s'));
        self::assertSame('Africa/Tunis', $window['from']->getTimezone()->getName());
    }

    public function testWindowFallsBackToDefaultOnInvalidDates(): void
    {
        $now = $this->at('2026-06-15 10:00:00');

        self::assertTrue($this->calculator->resolveWindow('not-a-date', '2026-01-05', $now)['isDefault']);
        self::assertTrue($this->calculator->resolveWindow('2026-02-31', '2026-03-05', $now)['isDefault']);
        self::assertTrue($this->calculator->resolveWindow('2026-05-01', '2026-04-01', $now)['isDefault']);
    }

    public function testWindowAcceptsValidRangeAndCapsItAtFiveYears(): void
    {
        $now = $this->at('2026-06-15 10:00:00');

        $valid = $this->calculator->resolveWindow('2026-01-01', '2026-01-10', $now);
        self::assertFalse($valid['isDefault']);
        self::assertSame('2026-01-01 00:00:00', $valid['from']->format('Y-m-d H:i:s'));
        self::assertSame('2026-01-10 23:59:59', $valid['to']->format('Y-m-d H:i:s'));

        $capped = $this->calculator->resolveWindow('2010-01-01', '2026-01-10', $now);
        self::assertFalse($capped['isDefault']);
        self::assertSame('2021-01-10', $capped['from']->format('Y-m-d'));
        self::assertSame('2026-01-10', $capped['to']->format('Y-m-d'));
    }

    public function testNoFaultsGivesFullAvailability(): void
    {
        [$from, $to] = $this->window();
        $rows = $this->calculator->availabilityPerMachine([$this->machine(1, '2025-01-01')], [], [], $from, $to);

        self::assertCount(1, $rows);
        self::assertSame(240.0, $rows[0]['windowHours']);
        self::assertSame(0.0, $rows[0]['downtimeHours']);
        self::assertSame(100.0, $rows[0]['availability']);
    }

    public function testResolvedCritiqueFaultDowntimeIsSubtracted(): void
    {
        [$from, $to] = $this->window();
        $faults = [$this->fault(1, 1, '2026-01-02 00:00:00', 'critique', 'resolue')];
        $interventions = [$this->intervention(1, 'curative', 'terminee', '2026-01-02 00:00:00', '2026-01-02 00:00:00', '2026-01-03 00:00:00')];

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2025-01-01')], $faults, $interventions, $from, $to))[1];

        self::assertSame(24.0, $row['downtimeHours']);
        self::assertSame(90.0, $row['availability']);
        self::assertSame(0, $row['faultsWithoutResolutionTimestamp']);
    }

    public function testOverlappingCritiqueFaultsAreMerged(): void
    {
        [$from, $to] = $this->window();
        $faults = [
            $this->fault(1, 1, '2026-01-02 00:00:00', 'critique', 'resolue'),
            $this->fault(2, 1, '2026-01-03 00:00:00', 'critique', 'resolue'),
        ];
        $interventions = [
            $this->intervention(1, 'curative', 'terminee', '2026-01-02 00:00:00', '2026-01-02 00:00:00', '2026-01-04 00:00:00'),
            $this->intervention(2, 'curative', 'terminee', '2026-01-03 00:00:00', '2026-01-03 00:00:00', '2026-01-05 00:00:00'),
        ];

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2025-01-01')], $faults, $interventions, $from, $to))[1];

        // union of [02,04) and [03,05) = [02,05) = 72 h (a naive sum would give 96 h = 60 %)
        self::assertSame(72.0, $row['downtimeHours']);
        self::assertSame(70.0, $row['availability']);
    }

    public function testOpenCritiqueFaultRunsToTheEndOfTheWindow(): void
    {
        [$from, $to] = $this->window();
        $faults = [$this->fault(1, 1, '2026-01-08 00:00:00', 'critique', 'en cours')];

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2025-01-01')], $faults, [], $from, $to))[1];

        self::assertSame(72.0, $row['downtimeHours']);
        self::assertSame(70.0, $row['availability']);
        self::assertSame(1, $row['openCritiqueFaults']);
    }

    public function testCritiqueFaultReportedBeforeWindowIsClippedToWindowStart(): void
    {
        [$from, $to] = $this->window();
        $faults = [$this->fault(1, 1, '2025-12-20 00:00:00', 'critique', 'en cours')];

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2025-01-01')], $faults, [], $from, $to))[1];

        self::assertSame(240.0, $row['downtimeHours']);
        self::assertSame(0.0, $row['availability']);
    }

    public function testMachineInstalledInsideTheWindowUsesItsInstallationDate(): void
    {
        [$from, $to] = $this->window();
        $faults = [$this->fault(1, 1, '2026-01-07 00:00:00', 'critique', 'resolue')];
        $interventions = [$this->intervention(1, 'curative', 'terminee', '2026-01-07 00:00:00', '2026-01-07 00:00:00', '2026-01-08 00:00:00')];

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2026-01-06 00:00:00')], $faults, $interventions, $from, $to))[1];

        self::assertSame(120.0, $row['windowHours']);
        self::assertSame(24.0, $row['downtimeHours']);
        self::assertSame(80.0, $row['availability']);
    }

    public function testMachineInstalledAfterTheWindowIsNotAvailable(): void
    {
        [$from, $to] = $this->window();

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2026-02-01 00:00:00')], [], [], $from, $to))[1];

        self::assertSame(0.0, $row['windowHours']);
        self::assertNull($row['availability']);
    }

    public function testFaultResolvedByResponsableWithoutInterventionAddsNoDowntimeButIsFlagged(): void
    {
        [$from, $to] = $this->window();
        $faults = [$this->fault(1, 1, '2026-01-05 00:00:00', 'critique', 'resolue')];

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2025-01-01')], $faults, [], $from, $to))[1];

        self::assertSame(0.0, $row['downtimeHours']);
        self::assertSame(100.0, $row['availability']);
        self::assertSame(1, $row['faultsWithoutResolutionTimestamp']);
    }

    public function testResolutionBeforeReportIsIgnored(): void
    {
        [$from, $to] = $this->window();
        $faults = [$this->fault(1, 1, '2026-01-05 00:00:00', 'critique', 'resolue')];
        $interventions = [$this->intervention(1, 'curative', 'terminee', '2026-01-03 00:00:00', '2026-01-03 00:00:00', '2026-01-03 06:00:00')];

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2025-01-01')], $faults, $interventions, $from, $to))[1];

        self::assertSame(0.0, $row['downtimeHours']);
        self::assertSame(100.0, $row['availability']);
    }

    public function testFaultResolvedBeforeTheWindowIsIgnored(): void
    {
        [$from, $to] = $this->window();
        $faults = [$this->fault(1, 1, '2025-06-01 00:00:00', 'critique', 'resolue')];
        $interventions = [$this->intervention(1, 'curative', 'terminee', '2025-06-01 00:00:00', '2025-06-01 00:00:00', '2025-06-02 00:00:00')];

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2025-01-01')], $faults, $interventions, $from, $to))[1];

        self::assertSame(0.0, $row['downtimeHours']);
        self::assertSame(100.0, $row['availability']);
    }

    public function testNonCritiqueFaultsAreNotDowntime(): void
    {
        [$from, $to] = $this->window();
        $faults = [
            $this->fault(1, 1, '2026-01-02 00:00:00', 'moyenne', 'en cours'),
            $this->fault(2, 1, '2026-01-03 00:00:00', 'faible', 'ouverte'),
        ];

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2025-01-01')], $faults, [], $from, $to))[1];

        self::assertSame(0.0, $row['downtimeHours']);
        self::assertSame(100.0, $row['availability']);
    }

    public function testDowntimeIsClampedSoAvailabilityNeverGoesBelowZero(): void
    {
        [$from, $to] = $this->window();
        $faults = [
            $this->fault(1, 1, '2025-12-01 00:00:00', 'critique', 'en cours'),
            $this->fault(2, 1, '2026-01-02 00:00:00', 'critique', 'ouverte'),
        ];

        $row = $this->byMachineId($this->calculator->availabilityPerMachine([$this->machine(1, '2025-01-01')], $faults, [], $from, $to))[1];

        self::assertSame(240.0, $row['downtimeHours']);
        self::assertSame(0.0, $row['availability']);
    }

    public function testMeanRepairTimeAveragesCurativeFinishedInterventions(): void
    {
        [$from, $to] = $this->window();
        $interventions = [
            $this->intervention(1, 'curative', 'terminee', '2026-01-02 08:00:00', '2026-01-02 08:00:00', '2026-01-02 12:00:00'),
            $this->intervention(2, 'curative', 'terminee', '2026-01-03 08:00:00', '2026-01-03 08:00:00', '2026-01-03 11:00:00'),
        ];

        $result = $this->calculator->meanRepairTime($interventions, $from, $to);

        self::assertSame(2, $result['count']);
        self::assertSame(7.0, $result['totalHours']);
        self::assertSame(3.5, $result['meanHours']);
    }

    public function testMeanRepairTimeExcludesPreventiveAndOpenInterventions(): void
    {
        [$from, $to] = $this->window();
        $interventions = [
            $this->intervention(null, 'preventive', 'terminee', '2026-01-02 08:00:00', '2026-01-02 08:00:00', '2026-01-02 15:00:00'),
            $this->intervention(1, 'curative', 'en cours', '2026-01-03 08:00:00', '2026-01-03 08:00:00', null),
            $this->intervention(2, 'curative', 'terminee', '2026-01-04 08:00:00', '2026-01-04 08:00:00', '2026-01-04 13:00:00'),
        ];

        $result = $this->calculator->meanRepairTime($interventions, $from, $to);

        self::assertSame(1, $result['count']);
        self::assertSame(5.0, $result['meanHours']);
    }

    public function testMeanRepairTimeFallsBackToStartDateWhenStartedAtIsNull(): void
    {
        [$from, $to] = $this->window();
        $interventions = [
            $this->intervention(1, 'curative', 'terminee', '2026-01-02 08:00:00', null, '2026-01-02 10:00:00'),
        ];

        $result = $this->calculator->meanRepairTime($interventions, $from, $to);

        self::assertSame(1, $result['count']);
        self::assertSame(2.0, $result['meanHours']);
    }

    public function testMeanRepairTimeIgnoresEndDatesOutsideTheWindowAndEmptyInput(): void
    {
        [$from, $to] = $this->window();
        $interventions = [
            $this->intervention(1, 'curative', 'terminee', '2026-02-01 08:00:00', '2026-02-01 08:00:00', '2026-02-01 10:00:00'),
            $this->intervention(2, 'curative', 'terminee', '2025-06-01 08:00:00', '2025-06-01 08:00:00', '2025-06-01 10:00:00'),
        ];

        $outside = $this->calculator->meanRepairTime($interventions, $from, $to);
        self::assertSame(0, $outside['count']);
        self::assertNull($outside['meanHours']);

        $empty = $this->calculator->meanRepairTime([], $from, $to);
        self::assertSame(0, $empty['count']);
        self::assertSame(0.0, $empty['totalHours']);
        self::assertNull($empty['meanHours']);
    }

    public function testFaultsPerSitePerMonthGroupsBySiteAndMonth(): void
    {
        [$from, $to] = $this->window();
        $machines = [
            $this->machine(1, '2025-01-01', 'Sousse'),
            $this->machine(2, '2025-01-01', 'Béja'),
        ];
        $faults = [
            $this->fault(1, 1, '2026-01-05 00:00:00', 'moyenne', 'resolue'),
            $this->fault(2, 1, '2026-01-09 00:00:00', 'faible', 'resolue'),
            $this->fault(3, 2, '2026-01-07 00:00:00', 'critique', 'resolue'),
            $this->fault(4, 2, '2025-12-31 00:00:00', 'critique', 'resolue'),
        ];

        $rows = $this->calculator->faultsPerSitePerMonth($faults, $machines, $from, $to, 'site');

        $counts = [];
        foreach ($rows as $row) {
            self::assertSame('site', $row['groupType']);
            self::assertNull($row['dataStatus']);
            $counts[$row['group'].'|'.$row['month']] = $row['count'];
        }

        self::assertEquals(['Béja|2026-01' => 1, 'Sousse|2026-01' => 2], $counts);
    }

    public function testFaultsPerModelPerMonthCarriesTheModelDataStatus(): void
    {
        [$from, $to] = $this->window();
        $machines = [
            $this->machine(1, '2025-01-01', 'Sousse', 'OC-CA1100', 'confirmed'),
            $this->machine(2, '2025-01-01', 'Béja', 'DEMO-PRESS', 'demo'),
        ];
        $faults = [
            $this->fault(1, 1, '2026-01-05 00:00:00', 'critique', 'resolue'),
            $this->fault(2, 2, '2026-01-06 00:00:00', 'critique', 'resolue'),
        ];

        $rows = $this->calculator->faultsPerSitePerMonth($faults, $machines, $from, $to, 'model');

        $groups = [];
        foreach ($rows as $row) {
            self::assertSame('model', $row['groupType']);
            $groups[$row['group']] = $row['dataStatus'];
        }

        self::assertEquals(['OC-CA1100' => 'confirmed', 'DEMO-PRESS' => 'demo'], $groups);
    }

    public function testFaultMonthsAreGroupedInTunisTimezone(): void
    {
        $from = $this->at('2026-01-01 00:00:00');
        $to = $this->at('2026-02-10 00:00:00');
        $machines = [$this->machine(1, '2025-01-01', 'Sousse')];
        $utc = new \DateTimeZone('UTC');
        $faults = [[
            'id' => 1,
            'machineId' => 1,
            'severity' => 'critique',
            'status' => 'resolue',
            'reportedAt' => new \DateTimeImmutable('2026-01-31 23:30:00', $utc),
        ]];

        $rows = $this->calculator->faultsPerSitePerMonth($faults, $machines, $from, $to, 'site');

        // 2026-01-31 23:30 UTC is 2026-02-01 00:30 in Africa/Tunis (UTC+1)
        self::assertSame('2026-02', $rows[0]['month']);
    }

    public function testComputeReturnsEveryKpiBlock(): void
    {
        [$from, $to] = $this->window();
        $result = $this->calculator->compute([$this->machine(1, '2025-01-01')], [], [], $from, $to);

        self::assertArrayHasKey('availability', $result);
        self::assertArrayHasKey('meanRepairTime', $result);
        self::assertArrayHasKey('faultsPerSite', $result);
        self::assertArrayHasKey('faultsPerModel', $result);
        self::assertSame(100.0, $result['availability'][0]['availability']);
    }
}
