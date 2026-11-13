<?php

namespace App\Tests\Unit;

use App\Entity\Intervention;
use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\MaintenanceSchedule;
use App\Entity\MaintenanceTask;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\InterventionRepository;
use App\Repository\MaintenanceScheduleRepository;
use App\Repository\UserRepository;
use App\Service\MaintenancePlanner;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MaintenancePlannerTest extends TestCase
{
    public function testDueScheduleCreatesOnePreventiveInterventionAndAssignsLeastLoadedTechnician(): void
    {
        [$schedule, $site] = $this->dueSchedule();
        $busy = (new User())->setFullName('Busy')->setRoles(['ROLE_TECHNICIEN'])->setSite($site);
        $available = (new User())->setFullName('Available')->setRoles(['ROLE_TECHNICIEN'])->setSite($site);

        $scheduleRepository = $this->createMock(MaintenanceScheduleRepository::class);
        $scheduleRepository->method('findAllForPlanning')->willReturn([$schedule]);
        $interventionRepository = $this->createMock(InterventionRepository::class);
        $interventionRepository->method('hasOpenPreventiveForSchedule')->willReturn(false);
        $interventionRepository->method('countOpenForTechnician')->willReturnMap([
            [$busy, 3],
            [$available, 0],
        ]);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findBy')->willReturn([$busy, $available]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $entityManager->expects(self::atLeastOnce())->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void { $persisted[] = $entity; }
        );
        $entityManager->expects(self::once())->method('flush');

        $result = (new MaintenancePlanner($scheduleRepository, $interventionRepository, $userRepository, $entityManager))
            ->check(new \DateTimeImmutable('2026-11-08 12:00:00'));

        self::assertSame(1, $result['created']);
        $interventions = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof Intervention));
        self::assertCount(1, $interventions);
        self::assertSame($available, $interventions[0]->getTechnician());
        self::assertSame('echue', $schedule->getState());
    }

    public function testDueScheduleDoesNotCreateDuplicateOpenIntervention(): void
    {
        [$schedule] = $this->dueSchedule();
        $scheduleRepository = $this->createMock(MaintenanceScheduleRepository::class);
        $scheduleRepository->method('findAllForPlanning')->willReturn([$schedule]);
        $interventionRepository = $this->createMock(InterventionRepository::class);
        $interventionRepository->expects(self::once())->method('hasOpenPreventiveForSchedule')->willReturn(true);
        $userRepository = $this->createMock(UserRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $entityManager->expects(self::never())->method('persist');

        $result = (new MaintenancePlanner($scheduleRepository, $interventionRepository, $userRepository, $entityManager))
            ->check(new \DateTimeImmutable('2026-11-08 12:00:00'));

        self::assertSame(0, $result['created']);
    }

    /** @return array{MaintenanceSchedule, Site} */
    private function dueSchedule(): array
    {
        $site = (new Site())->setName('Sousse')->setCity('Sousse');
        $machineModel = (new MachineModel())->setCode('OC-CA1100')->setName('OC-CA1100')->setFamily('Convoyeur');
        $machine = (new Machine())->setName('OC-CA1100')->setMachineModel($machineModel)
            ->setSerialNumber('SERIAL #TEST')->setInstallationDate(new \DateTime('2026-01-01'))
            ->setCategory('Convoyeur aérien')->setStatus('en service')->setSite($site);
        $task = (new MaintenanceTask())->setMachineModel($machineModel)->setAction('vérifier le bruit')->setFrequencyDays(7)->setFrequencyHours(168);
        $schedule = (new MaintenanceSchedule())->initializeFromMachine(
            $machine,
            $task,
            new \DateTime('2026-10-20')
        );

        return [$schedule, $site];
    }
}
