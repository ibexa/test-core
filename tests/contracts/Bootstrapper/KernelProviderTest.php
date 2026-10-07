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
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

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

    /**
     * @testWith [null, "The kernel class \"null\" must implement \"Symfony\\Component\\HttpKernel\\KernelInterface\". Ensure that the KERNEL_CLASS environment variable is set to a valid test kernel class."]
     *           ["stdClass", "The kernel class \"stdClass\" must implement \"Symfony\\Component\\HttpKernel\\KernelInterface\". Ensure that the KERNEL_CLASS environment variable is set to a valid test kernel class."]
     */
    public function testThrowsWhenKernelClassIsInvalid(
        ?string $kernelClass,
        string $exceptionMessage
    ): void {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($exceptionMessage);

        (new KernelProvider())->getKernel($kernelClass);
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

    public function testAcceptsAnyKernelInterfaceImplementation(): void
    {
        $kernel = (new KernelProvider())->getKernel(NoopBootPlainKernel::class);

        self::assertInstanceOf(NoopBootPlainKernel::class, $kernel);
        self::assertTrue($kernel->didBoot);
    }

    public function testBootsTheReturnedKernel(): void
    {
        $kernel = (new KernelProvider())->getKernel(NoopBootTestKernel::class);

        self::assertInstanceOf(NoopBootTestKernel::class, $kernel);
        self::assertTrue($kernel->didBoot);
    }
}

final class NoopBootPlainKernel extends Kernel
{
    public bool $didBoot = false;

    public function __construct()
    {
        parent::__construct('test', true);
    }

    public function boot(): void
    {
        $this->didBoot = true;
    }

    public function registerBundles(): iterable
    {
        return [];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void {}
}

final class NoopBootTestKernel extends IbexaTestKernel
{
    public bool $didBoot = false;

    public function boot(): void
    {
        $this->didBoot = true;
    }
}
