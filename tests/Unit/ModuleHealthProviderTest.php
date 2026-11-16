<?php

namespace App\Tests\Unit;

use App\Service\ModuleHealthProvider;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for ModuleHealthProvider business logic.
 */
final class ModuleHealthProviderTest extends TestCase
{
    public function testWorstHealthPriority(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $provider = new ModuleHealthProvider($em);

        self::assertSame('echue', $provider->worstHealth('ok', 'echue'));
        self::assertSame('echue', $provider->worstHealth('proche', 'echue'));
        self::assertSame('echue', $provider->worstHealth('echue', 'proche'));
        self::assertSame('proche', $provider->worstHealth('ok', 'proche'));
        self::assertSame('proche', $provider->worstHealth('proche', 'ok'));
        self::assertSame('ok', $provider->worstHealth('ok', 'ok'));
    }
}
