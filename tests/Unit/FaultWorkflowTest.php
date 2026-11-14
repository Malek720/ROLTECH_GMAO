<?php

namespace App\Tests\Unit;

use App\Entity\Fault;
use App\Entity\Intervention;
use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\FaultRepository;
use App\Repository\InterventionRepository;
use App\Repository\UserRepository;
use App\Service\FaultWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class FaultWorkflowTest extends TestCase
{
    public function testCriticalFaultCreatesInterventionAndNotifications(): void
    {
        $site = (new Site())->setName('Sousse')->setCity('Sousse');
        $machineModel = (new MachineModel())->setCode('OC-CA1100')->setName('OC-CA1100')->setFamily('Convoyeur');
        $machine = (new Machine())->setName('OC-CA1100')->setMachineModel($machineModel)
            ->setSerialNumber('SERIAL #TEST')->setInstallationDate(new \DateTime('2026-01-01'))
            ->setCategory('Convoyeur aérien')->setStatus('en service')->setSite($site);
        $reporter = (new User())->setFullName('Reporter')->setRoles(['ROLE_TECHNICIEN'])->setSite($site);
        $responsable = (new User())->setFullName('Responsable')->setRoles(['ROLE_RESPONSABLE'])->setSite($site);
        $technician = (new User())->setFullName('Technicien')->setRoles(['ROLE_TECHNICIEN'])->setSite($site);

        $faultRepository = $this->createMock(FaultRepository::class);
        $faultRepository->method('findOneBy')->willReturn(null);
        $interventionRepository = $this->createMock(InterventionRepository::class);
        $interventionRepository->method('countOpenForTechnician')->with($technician)->willReturn(0);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findBy')->willReturn([$responsable, $technician]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $persisted = [];
        $entityManager->expects(self::atLeast(3))->method('persist')->willReturnCallback(
            static function (object $entity) use (&$persisted): void { $persisted[] = $entity; }
        );
        $entityManager->expects(self::once())->method('flush');

        $fault = (new FaultWorkflow($faultRepository, $interventionRepository, $userRepository, $entityManager))->declare(
            $reporter,
            $machine,
            null,
            null,
            'Motor or reducer overheating',
            'Température excessive constatée.',
            'critique',
        );

        self::assertSame('en panne', $machine->getStatus());
        self::assertSame('ouverte', $fault->getStatus());
        self::assertCount(1, array_filter($persisted, static fn (object $entity): bool => $entity instanceof Intervention));
        self::assertCount(2, array_filter($persisted, static fn (object $entity): bool => $entity instanceof \App\Entity\Notification));
    }

    public function testSubmissionTokenReturnsExistingFaultWithoutDuplicatingRows(): void
    {
        $existing = new Fault();
        $faultRepository = $this->createMock(FaultRepository::class);
        $faultRepository->expects(self::once())
            ->method('findOneBy')
            ->with(['submissionToken' => 'a1b2c3'])
            ->willReturn($existing);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $result = (new FaultWorkflow(
            $faultRepository,
            $this->createMock(InterventionRepository::class),
            $this->createMock(UserRepository::class),
            $entityManager,
        ))->declare(
            new User(),
            new Machine(),
            null,
            null,
            'autre',
            'Doublon de soumission',
            'faible',
            'a1b2c3',
        );

        self::assertSame($existing, $result);
    }
}
