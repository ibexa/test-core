<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Doctrine\DBAL\Connection;
use Ibexa\Contracts\Core\Test\Persistence\Fixture\FixtureImporter;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\DoctrineSchema\DbPlatformFactoryInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\BaseFixtureHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\Bootstrapper;
use Ibexa\Contracts\Test\Core\Bootstrapper\DatabasePreparerInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\DatabaseSchemaHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\DefaultFixtureProvider;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureProviderInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\KernelProviderInterface;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use Ibexa\Test\Core\Bootstrapper\HooksExecutor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

#[CoversClass(Bootstrapper::class)]
final class BootstrapperTest extends TestCase
{
    /**
     * @var IbexaTestKernel&MockObject
     */
    private IbexaTestKernel $kernel;

    /**
     * @var HooksExecutorInterface&MockObject
     */
    private HooksExecutorInterface $hooksExecutor;

    /**
     * @var KernelProviderInterface&MockObject
     */
    private KernelProviderInterface $kernelProvider;

    /**
     * @var DatabasePreparerInterface&MockObject
     */
    private DatabasePreparerInterface $databasePreparer;

    private Bootstrapper $bootstrapper;

    protected function setUp(): void
    {
        $testContainer = $this->createMock(ContainerInterface::class);
        $this->hooksExecutor = $this->createMock(HooksExecutorInterface::class);
        $this->hooksExecutor
            ->expects(self::once())
            ->method('configureOptions')
            ->willReturnCallback(static function (OptionsResolver $resolver): void {});

        $testContainer
            ->expects(self::once())
            ->method('get')
            ->with(HooksExecutorInterface::class)
            ->willReturn($this->hooksExecutor);

        $container = $this->createMock(ContainerInterface::class);
        $container
            ->expects(self::once())
            ->method('get')
            ->with('test.service_container')->willReturn($testContainer);

        $this->kernel = $this->createMock(IbexaTestKernel::class);
        $this->kernel
            ->expects(self::once())
            ->method('getContainer')
            ->willReturn($container);

        $this->kernelProvider = $this->createMock(KernelProviderInterface::class);
        $this->kernelProvider
            ->expects(self::once())
            ->method('getKernel')
            ->willReturn($this->kernel);

        $this->databasePreparer = $this->createMock(DatabasePreparerInterface::class);

        $this->bootstrapper = new Bootstrapper($this->kernelProvider, $this->databasePreparer);
    }

    public function testDelegatesKernelResolutionToTheProviderWithTheGivenKernelClass(): void
    {
        $this->kernelProvider
            ->expects(self::once())
            ->method('getKernel')
            ->with('My\\Kernel')
            ->willReturn($this->kernel);

        $this->bootstrapper->bootstrap('My\\Kernel');
    }

    public function testReturnsTheKernelObtainedFromTheProvider(): void
    {
        self::assertSame($this->kernel, $this->bootstrapper->bootstrap());
    }

    public function testPreparesTheDatabaseWithoutSchemaUpdateByDefault(): void
    {
        $this->databasePreparer
            ->expects(self::once())
            ->method('prepareDatabase')
            ->with($this->kernel, false);

        $this->bootstrapper->bootstrap();
    }

    public function testPreparesTheDatabaseWithSchemaUpdateWhenOptedIn(): void
    {
        $this->databasePreparer
            ->expects(self::once())
            ->method('prepareDatabase')
            ->with($this->kernel, true);

        $this->bootstrapper->bootstrap(null, [
            Bootstrapper::class => [Bootstrapper::OPTION_SCHEMA_UPDATE => true],
        ]);
    }

    public function testSkipsDatabasePreparationEntirelyWhenOptedOut(): void
    {
        $this->databasePreparer
            ->expects(self::never())
            ->method('prepareDatabase');

        $this->bootstrapper->bootstrap(null, [
            Bootstrapper::class => [Bootstrapper::OPTION_PREPARE_DATABASE => false],
        ]);
    }

    public function testExecutesHooksWithTheFullyResolvedOptionsArray(): void
    {
        $this->hooksExecutor
            ->expects(self::once())
            ->method('configureOptions')
            ->willReturnCallback(static function (OptionsResolver $resolver): void {
                $resolver->define('some.hook.id')->default([])->allowedTypes('array');
            });

        $this->hooksExecutor
            ->expects(self::once())
            ->method('execute')
            ->with([
                Bootstrapper::class => [
                    Bootstrapper::OPTION_PREPARE_DATABASE => true,
                    Bootstrapper::OPTION_SCHEMA_UPDATE => false,
                    Bootstrapper::OPTION_SHUTDOWN_KERNEL => true,
                ],
                'some.hook.id' => ['enabled' => false],
            ]);

        $this->bootstrapper->bootstrap(null, [
            'some.hook.id' => ['enabled' => false],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('provideOptionsForBaseFixtureSwitch')]
    public function testBaseFixtureSwitchFollowsFixtureHookUnlessPassedExplicitly(
        array $options,
        bool $expectedLoadBaseFixture
    ): void {
        $hooksExecutor = $this->createHooksExecutor($this->createStub(Connection::class));
        $this->hooksExecutor
            ->expects(self::once())
            ->method('configureOptions')
            ->willReturnCallback([$hooksExecutor, 'configureOptions']);

        $executedWith = [];
        $this->hooksExecutor
            ->expects(self::once())
            ->method('execute')
            ->willReturnCallback(static function (array $options) use (&$executedWith): void {
                $executedWith = $options;
            });

        $this->bootstrapper->bootstrap(null, $options);

        self::assertSame(
            $expectedLoadBaseFixture,
            $executedWith[BaseFixtureHook::class][BaseFixtureHook::OPTION_LOAD_BASE_FIXTURE]
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function provideOptionsForBaseFixtureSwitch(): iterable
    {
        yield 'fixtures on by default' => [[], true];

        yield 'fixtures switched off' => [
            [FixtureHook::class => [FixtureHook::OPTION_LOAD_FIXTURES => false]],
            false,
        ];

        yield 'baseline switched off explicitly, fixtures on' => [
            [BaseFixtureHook::class => [BaseFixtureHook::OPTION_LOAD_BASE_FIXTURE => false]],
            false,
        ];

        yield 'baseline switched on explicitly, fixtures off' => [
            [
                FixtureHook::class => [FixtureHook::OPTION_LOAD_FIXTURES => false],
                BaseFixtureHook::class => [BaseFixtureHook::OPTION_LOAD_BASE_FIXTURE => true],
            ],
            true,
        ];
    }

    /**
     * The bootstrap a package whose tests never touch the database uses: no database preparation,
     * no schema, no fixtures. Runs the real built-in hooks, so the baseline import would fail on the
     * missing schema if it still ran.
     */
    public function testBootstrapWithEveryDatabaseStepSwitchedOffNeverTouchesTheDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method(self::anything());

        $hooksExecutor = $this->createHooksExecutor($connection);
        $this->hooksExecutor
            ->expects(self::once())
            ->method('configureOptions')
            ->willReturnCallback([$hooksExecutor, 'configureOptions']);
        $this->hooksExecutor
            ->expects(self::once())
            ->method('execute')
            ->willReturnCallback([$hooksExecutor, 'execute']);

        $this->bootstrapper->bootstrap(null, [
            Bootstrapper::class => [Bootstrapper::OPTION_PREPARE_DATABASE => false],
            DatabaseSchemaHook::class => [DatabaseSchemaHook::OPTION_LOAD_SCHEMA => false],
            FixtureHook::class => [FixtureHook::OPTION_LOAD_FIXTURES => false],
        ]);
    }

    public function testShutsDownTheKernelByDefault(): void
    {
        $this->kernel
            ->expects(self::once())
            ->method('shutdown');

        $this->bootstrapper->bootstrap();
    }

    public function testSkipsKernelShutdownWhenOptedOut(): void
    {
        $this->kernel
            ->expects(self::never())
            ->method('shutdown');

        $this->bootstrapper->bootstrap(null, [
            Bootstrapper::class => [Bootstrapper::OPTION_SHUTDOWN_KERNEL => false],
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    #[TestWith([['some.unknown.key' => []]])]
    #[TestWith([['Ibexa\Contracts\Test\Core\Bootstrapper\Bootstrapper' => ['some_unknown_option' => true]]])]
    public function testRejectsAnUnrecognizedOptions(array $options): void
    {
        $this->expectException(UndefinedOptionsException::class);

        $this->bootstrapper->bootstrap(null, $options);
    }

    /**
     * The built-in database hooks, keyed by service id as the container registers them.
     */
    private function createHooksExecutor(Connection $connection): HooksExecutor
    {
        $fixtureImporter = new FixtureImporter($connection);

        return new HooksExecutor([
            DatabaseSchemaHook::class => new DatabaseSchemaHook(
                $this->createStub(SchemaBuilderInterface::class),
                $connection,
                $this->createStub(DbPlatformFactoryInterface::class)
            ),
            BaseFixtureHook::class => new BaseFixtureHook(new DefaultFixtureProvider(), $fixtureImporter),
            FixtureHook::class => new FixtureHook(
                $this->createStub(FixtureProviderInterface::class),
                $fixtureImporter
            ),
        ]);
    }
}
