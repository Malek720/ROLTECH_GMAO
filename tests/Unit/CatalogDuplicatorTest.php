<?php

namespace App\Tests\Unit;

use App\Entity\MachineModel;
use App\Entity\MachineModule;
use App\Entity\Part;
use App\Service\CatalogDuplicator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

final class CatalogDuplicatorTest extends TestCase
{
    public function testDuplicateKeepsNestingAndDemoStatusWithoutPartNumbers(): void
    {
        $source = (new MachineModel())->setCode('OC-CA1100')->setName('Source')->setFamily('Convoyeur')->setDataStatus('confirmed');
        $root = (new MachineModule())->setMachineModel($source)->setCode('M1')->setName('Root')->setPlanche('P1')->setDataStatus('confirmed');
        $child = (new MachineModule())->setMachineModel($source)->setCode('M2')->setName('Child')->setPlanche('P2')->setParent($root)->setDataStatus('confirmed');
        $root->getChildren()->add($child);
        $root->getParts()->add((new Part())->setModule($root)->setPartNumber('REAL-1')->setDescription('Part')->setDataStatus('confirmed'));
        $source->getModules()->add($root);
        $source->getModules()->add($child);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->method('persist');
        $connection = $this->createMock(Connection::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $copy = (new CatalogDuplicator($entityManager))->duplicate($source, 'NEW', 'Copy');

        self::assertSame('NEW', $copy->getCode());
        self::assertSame('demo', $copy->getDataStatus());
        self::assertCount(2, $copy->getModules());
        self::assertSame($copy, $copy->getModules()->first()->getMachineModel());
        self::assertSame($copy->getModules()->first(), $copy->getModules()->last()->getParent());
        self::assertSame(null, $copy->getModules()->first()->getParts()->first()->getPartNumber());
        self::assertCount(0, $copy->getMachines());
        self::assertCount(0, $copy->getMaintenanceTasks());
    }

    public function testDuplicateAddsCopySuffixWhenCodeExists(): void
    {
        $source = (new MachineModel())->setCode('SRC')->setName('Source')->setFamily('Test');
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(self::exactly(3))->method('findOneBy')->willReturnOnConsecutiveCalls(
            (new MachineModel())->setCode('NEW-COPY-2'),
            (new MachineModel())->setCode('NEW-COPY'),
            null,
        );
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);
        $entityManager->method('persist');
        $connection = $this->createMock(Connection::class);
        $entityManager->method('getConnection')->willReturn($connection);

        $copy = (new CatalogDuplicator($entityManager))->duplicate($source, 'NEW', 'Copy');

        self::assertSame('NEW-COPY-2', $copy->getCode());
        self::assertCount(0, $copy->getMachines());
        self::assertCount(0, $copy->getMaintenanceTasks());
    }
}
