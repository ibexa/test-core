<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\Bootstrapper;
use Ibexa\Contracts\Test\Core\Bootstrapper\DatabasePreparerInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\KernelProviderInterface;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\Bootstrapper
 */
final class BootstrapperTest extends TestCase
{
    /**
     * @var \Ibexa\Contracts\Test\Core\IbexaTestKernel&\PHPUnit\Framework\MockObject\MockObject
     */
    private $kernel;

    /**
     * @var \Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $hooksExecutor;

    /**
     * @var \Ibexa\Contracts\Test\Core\Bootstrapper\KernelProviderInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $kernelProvider;

    /**
     * @var \Ibexa\Contracts\Test\Core\Bootstrapper\DatabasePreparerInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $databasePreparer;

    private Bootstrapper $bootstrapper;

    protected function setUp(): void
    {
        $testContainer = $this->createMock(ContainerInterface::class);
        $this->hooksExecutor = $this->createMock(HooksExecutorInterface::class);
        $this->hooksExecutor->method('configureOptions')->willReturnCallback(static function (OptionsResolver $resolver): void {
        });
        $testContainer->method('get')->with(HooksExecutorInterface::class)->willReturn($this->hooksExecutor);

        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with('test.service_container')->willReturn($testContainer);

        $this->kernel = $this->createMock(IbexaTestKernel::class);
        $this->kernel->method('getContainer')->willReturn($container);

        $this->kernelProvider = $this->createMock(KernelProviderInterface::class);
        $this->kernelProvider->method('getKernel')->willReturn($this->kernel);

        $this->databasePreparer = $this->createMock(DatabasePreparerInterface::class);

        $this->bootstrapper = new Bootstrapper($this->kernelProvider, $this->databasePreparer);
    }

    public function testDelegatesKernelResolutionToTheProviderWithTheGivenKernelClass(): void
    {
        $this->kernelProvider->expects(self::once())
            ->method('getKernel')
            ->with('My\\Kernel')
            ->willReturn($this->kernel);

        $this->bootstrapper->bootstrap('My\\Kernel');
    }

    public function testReturnsTheKernelObtainedFromTheProvider(): void
    {
        self::assertSame($this->kernel, $this->bootstrapper->bootstrap());
    }

    public function testPreparesTheDatabaseWithTheDefaultSchemaUpdateOption(): void
    {
        $this->databasePreparer->expects(self::once())
            ->method('prepareDatabase')
            ->with($this->kernel, true);

        $this->bootstrapper->bootstrap();
    }

    public function testPreparesTheDatabaseWithSchemaUpdateDisabledWhenOptedOut(): void
    {
        $this->databasePreparer->expects(self::once())
            ->method('prepareDatabase')
            ->with($this->kernel, false);

        $this->bootstrapper->bootstrap(null, [
            Bootstrapper::class => [Bootstrapper::OPTION_SCHEMA_UPDATE => false],
        ]);
    }

    public function testSkipsDatabasePreparationEntirelyWhenOptedOut(): void
    {
        $this->databasePreparer->expects(self::never())->method('prepareDatabase');

        $this->bootstrapper->bootstrap(null, [
            Bootstrapper::class => [Bootstrapper::OPTION_PREPARE_DATABASE => false],
        ]);
    }

    public function testExecutesHooksWithTheFullyResolvedOptionsArray(): void
    {
        $this->hooksExecutor->method('configureOptions')->willReturnCallback(static function (OptionsResolver $resolver): void {
            $resolver->define('some.hook.id')->default([])->allowedTypes('array');
        });

        $this->hooksExecutor->expects(self::once())
            ->method('execute')
            ->with([
                Bootstrapper::class => [
                    Bootstrapper::OPTION_PREPARE_DATABASE => true,
                    Bootstrapper::OPTION_SCHEMA_UPDATE => true,
                    Bootstrapper::OPTION_SHUTDOWN_KERNEL => true,
                ],
                'some.hook.id' => ['enabled' => false],
            ]);

        $this->bootstrapper->bootstrap(null, [
            'some.hook.id' => ['enabled' => false],
        ]);
    }

    public function testShutsDownTheKernelByDefault(): void
    {
        $this->kernel->expects(self::once())->method('shutdown');

        $this->bootstrapper->bootstrap();
    }

    public function testSkipsKernelShutdownWhenOptedOut(): void
    {
        $this->kernel->expects(self::never())->method('shutdown');

        $this->bootstrapper->bootstrap(null, [
            Bootstrapper::class => [Bootstrapper::OPTION_SHUTDOWN_KERNEL => false],
        ]);
    }

    public function testRejectsAnUnrecognizedTopLevelKey(): void
    {
        $this->expectException(UndefinedOptionsException::class);

        $this->bootstrapper->bootstrap(null, ['some.unknown.key' => []]);
    }

    public function testRejectsAnUnrecognizedOptionUnderItsOwnKey(): void
    {
        $this->expectException(UndefinedOptionsException::class);

        $this->bootstrapper->bootstrap(null, [
            Bootstrapper::class => ['some_unknown_option' => true],
        ]);
    }
}
