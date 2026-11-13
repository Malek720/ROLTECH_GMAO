<?php

namespace App\Tests\Unit;

use App\Entity\Fault;
use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\Part;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\InterventionRepository;
use App\Repository\UserRepository;
use App\Service\FaultWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class MultiModelCatalogTest extends TestCase
{
    public function testCrossModelFaultModuleIsRejected(): void
    {
        $machineModel = $this->model('OC-CA1100');
        $otherModel = $this->model('HP-2000X');
        $machine = (new Machine())->setName('Machine')->setMachineModel($machineModel)
            ->setSerialNumber('TEST')->setInstallationDate(new \DateTime('2026-01-01'))
            ->setCategory('Demo')->setStatus('en service');
        $module = (new MachineModule())->setCode('M1')->setName('Module')->setPlanche('M1')->setMachineModel($otherModel);
        $workflow = new FaultWorkflow(
            $this->createMock(InterventionRepository::class),
            $this->createMock(UserRepository::class),
            $this->createMock(EntityManagerInterface::class),
        );

        $this->expectException(\InvalidArgumentException::class);
        $workflow->declare(
            (new User())->setFullName('Reporter'),
            $machine,
            $module,
            null,
            'autre',
            'Description',
            'faible',
        );
    }

    public function testMigrationBackfillsBeforeDroppingLegacyModelColumns(): void
    {
        $migration = file_get_contents(__DIR__.'/../../migrations/Version20261113123000.php');
        self::assertIsString($migration);
        self::assertLessThan(
            strpos($migration, "ALTER TABLE machine DROP model"),
            strpos($migration, "UPDATE machine SET machine_model_id"),
        );
        self::assertStringContainsString("UPDATE machine_module SET machine_model_id", $migration);
        self::assertStringContainsString("UPDATE maintenance_task SET machine_model_id", $migration);
        self::assertStringContainsString("DROP model", $migration);
    }

    private function model(string $code): MachineModel
    {
        return (new MachineModel())->setCode($code)->setName($code)->setFamily('Demo');
    }
}
