<?php

namespace App\Tests\Unit;

use App\Entity\Fault;
use App\Entity\Machine;
use App\Entity\MachineModel;
use App\Entity\MaintenanceSchedule;
use App\Entity\Site;
use App\Entity\User;
use App\Repository\SiteRepository;
use App\Service\SiteMapDataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for SiteMapDataProvider business logic.
 */
final class SiteMapDataProviderTest extends TestCase
{
    private SiteMapDataProvider $provider;

    protected function setUp(): void
    {
        $siteRepo = $this->createMock(SiteRepository::class);
        $this->provider = new SiteMapDataProvider($siteRepo);
    }

    public function testWorstColorRanking(): void
    {
        self::assertSame('red', $this->provider->worstColor('green', 'red'));
        self::assertSame('red', $this->provider->worstColor('orange', 'red'));
        self::assertSame('red', $this->provider->worstColor('red', 'green'));
        self::assertSame('orange', $this->provider->worstColor('green', 'orange'));
        self::assertSame('orange', $this->provider->worstColor('orange', 'green'));
        self::assertSame('green', $this->provider->worstColor('green', 'green'));
    }

    public function testMachineEnPanneGivesRed(): void
    {
        $site = (new Site())->setName('Sousse')->setCity('Sousse')->setLatitude(35.8)->setLongitude(10.6);
        $model = (new MachineModel())->setCode('M1')->setName('Modèle 1');
        $machine = (new Machine())->setName('Convoyeur 1')->setMachineModel($model)->setSerialNumber('SN-1')->setStatus('en panne');
        $site->getMachines()->add($machine);

        $siteRepo = $this->createMock(SiteRepository::class);
        $siteRepo->method('findAll')->willReturn([$site]);

        $provider = new SiteMapDataProvider($siteRepo);
        $admin = (new User())->setRoles(['ROLE_ADMIN']);

        $data = $provider->forUser($admin);
        self::assertCount(1, $data);
        self::assertSame('red', $data[0]['color']);
    }

    public function testOpenCritiqueFaultGivesRed(): void
    {
        $site = (new Site())->setName('Tunis')->setCity('Tunis');
        $model = (new MachineModel())->setCode('M2')->setName('Modèle 2');
        $machine = (new Machine())->setName('Convoyeur 2')->setMachineModel($model)->setSerialNumber('SN-2')->setStatus('en service');
        $fault = (new Fault())->setSeverity('critique')->setStatus('ouverte')->setDescription('Panne critique');
        $machine->getFaults()->add($fault);
        $site->getMachines()->add($machine);

        $siteRepo = $this->createMock(SiteRepository::class);
        $siteRepo->method('findAll')->willReturn([$site]);

        $provider = new SiteMapDataProvider($siteRepo);
        $admin = (new User())->setRoles(['ROLE_ADMIN']);

        $data = $provider->forUser($admin);
        self::assertSame('red', $data[0]['color']);
        self::assertSame(1, $data[0]['open_fault_count']);
    }

    public function testActiveScheduleEchueOrProcheGivesOrange(): void
    {
        $site = (new Site())->setName('Béja')->setCity('Béja');
        $model = (new MachineModel())->setCode('M3')->setName('Modèle 3');
        $machine = (new Machine())->setName('Convoyeur 3')->setMachineModel($model)->setSerialNumber('SN-3')->setStatus('en service');
        $schedule = (new MaintenanceSchedule())->setState('echue');
        $machine->getMaintenanceSchedules()->add($schedule);
        $site->getMachines()->add($machine);

        $siteRepo = $this->createMock(SiteRepository::class);
        $siteRepo->method('findAll')->willReturn([$site]);

        $provider = new SiteMapDataProvider($siteRepo);
        $resp = (new User())->setRoles(['ROLE_RESPONSABLE']);

        $data = $provider->forUser($resp);
        self::assertSame('orange', $data[0]['color']);
    }

    public function testArchivedScheduleDoesNotAffectColor(): void
    {
        $site = (new Site())->setName('Jendouba')->setCity('Jendouba');
        $model = (new MachineModel())->setCode('M4')->setName('Modèle 4');
        $machine = (new Machine())->setName('Convoyeur 4')->setMachineModel($model)->setSerialNumber('SN-4')->setStatus('en service');
        $archivedSchedule = (new MaintenanceSchedule())->setState('echue')->setArchivedAt(new \DateTime('-1 month'));
        $machine->getMaintenanceSchedules()->add($archivedSchedule);
        $site->getMachines()->add($machine);

        $siteRepo = $this->createMock(SiteRepository::class);
        $siteRepo->method('findAll')->willReturn([$site]);

        $provider = new SiteMapDataProvider($siteRepo);
        $resp = (new User())->setRoles(['ROLE_RESPONSABLE']);

        $data = $provider->forUser($resp);
        self::assertSame('green', $data[0]['color']);
    }

    public function testCanViewSitePermissions(): void
    {
        $site1 = new Site();
        (new \ReflectionProperty(Site::class, 'id'))->setValue($site1, 10);
        $site2 = new Site();
        (new \ReflectionProperty(Site::class, 'id'))->setValue($site2, 20);

        $admin = (new User())->setRoles(['ROLE_ADMIN']);
        $resp = (new User())->setRoles(['ROLE_RESPONSABLE']);
        $techWithSite1 = (new User())->setRoles(['ROLE_TECHNICIEN'])->setSite($site1);
        $techWithNoSite = (new User())->setRoles(['ROLE_TECHNICIEN']);

        self::assertTrue($this->provider->canViewSite($admin, $site1));
        self::assertTrue($this->provider->canViewSite($admin, $site2));
        self::assertTrue($this->provider->canViewSite($resp, $site1));
        self::assertTrue($this->provider->canViewSite($resp, $site2));

        self::assertTrue($this->provider->canViewSite($techWithSite1, $site1));
        self::assertFalse($this->provider->canViewSite($techWithSite1, $site2));
        self::assertFalse($this->provider->canViewSite($techWithNoSite, $site1));
    }
}
