<?php

namespace App\Tests\Unit;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\MaintenanceSchedule;
use PHPUnit\Framework\TestCase;

final class InterventionTest extends TestCase
{
    public function testAnInterventionRequiresExactlyOneSource(): void
    {
        $intervention = new Intervention();

        $this->expectException(\InvalidArgumentException::class);
        $intervention->validateSource();
    }

    public function testAnInterventionCannotHaveBothSources(): void
    {
        $intervention = (new Intervention())
            ->setFault(new Fault())
            ->setSchedule(new MaintenanceSchedule());

        $this->expectException(\InvalidArgumentException::class);
        $intervention->validateSource();
    }

    public function testFaultAndScheduleAreValidSources(): void
    {
        $faultIntervention = (new Intervention())->setFault(new Fault());
        $scheduleIntervention = (new Intervention())->setSchedule(new MaintenanceSchedule());

        $faultIntervention->validateSource();
        $scheduleIntervention->validateSource();

        self::assertTrue(true);
    }
}
