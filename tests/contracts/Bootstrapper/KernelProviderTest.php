<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\KernelProvider;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\KernelProvider
 */
final class KernelProviderTest extends TestCase
{
    /**
     * @var mixed
     */
    private $originalEnvKernelClass;

    /**
     * @var mixed
     */
    private $originalServerKernelClass;

    protected function setUp(): void
    {
        $this->originalEnvKernelClass = $_ENV['KERNEL_CLASS'] ?? null;
        $this->originalServerKernelClass = $_SERVER['KERNEL_CLASS'] ?? null;
        unset($_ENV['KERNEL_CLASS'], $_SERVER['KERNEL_CLASS']);
    }

    protected function tearDown(): void
    {
        if ($this->originalEnvKernelClass !== null) {
            $_ENV['KERNEL_CLASS'] = $this->originalEnvKernelClass;
        } else {
            unset($_ENV['KERNEL_CLASS']);
        }

        if ($this->originalServerKernelClass !== null) {
            $_SERVER['KERNEL_CLASS'] = $this->originalServerKernelClass;
        } else {
            unset($_SERVER['KERNEL_CLASS']);
        }
    }

    public function testThrowsWhenKernelClassIsNullAndNoEnvOrServerFallbackIsSet(): void
    {
        $this->expectException(LogicException::class);

        (new KernelProvider())->getKernel(null);
    }

    public function testThrowsWhenKernelClassIsNotASubclassOfIbexaTestKernel(): void
    {
        $this->expectException(LogicException::class);

        (new KernelProvider())->getKernel(stdClass::class);
    }

    public function testFallsBackToEnvKernelClassWhenArgumentIsNull(): void
    {
        $_ENV['KERNEL_CLASS'] = NoopBootTestKernel::class;

        $kernel = (new KernelProvider())->getKernel(null);

        self::assertInstanceOf(NoopBootTestKernel::class, $kernel);
    }

    public function testFallsBackToServerKernelClassWhenArgumentAndEnvAreNull(): void
    {
        $_SERVER['KERNEL_CLASS'] = NoopBootTestKernel::class;

        $kernel = (new KernelProvider())->getKernel(null);

        self::assertInstanceOf(NoopBootTestKernel::class, $kernel);
    }

    public function testArgumentTakesPrecedenceOverEnvAndServerFallbacks(): void
    {
        $_ENV['KERNEL_CLASS'] = stdClass::class;
        $_SERVER['KERNEL_CLASS'] = stdClass::class;

        $kernel = (new KernelProvider())->getKernel(NoopBootTestKernel::class);

        self::assertInstanceOf(NoopBootTestKernel::class, $kernel);
    }

    public function testBootsTheReturnedKernel(): void
    {
        $kernel = (new KernelProvider())->getKernel(NoopBootTestKernel::class);

        self::assertInstanceOf(NoopBootTestKernel::class, $kernel);
        self::assertTrue($kernel->didBoot);
    }
}

final class NoopBootTestKernel extends IbexaTestKernel
{
    public bool $didBoot = false;

    public function boot(): void
    {
        $this->didBoot = true;
    }
}
