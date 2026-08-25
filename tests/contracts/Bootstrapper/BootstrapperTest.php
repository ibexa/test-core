<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\Bootstrapper;
use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\Bootstrapper
 */
final class BootstrapperTest extends TestCase
{
    public function testDefaultsItsOwnOptionsWhenNotProvided(): void
    {
        $resolved = $this->resolveOptions($this->hooksExecutorDefiningNothingExtra(), []);

        self::assertSame([
            Bootstrapper::class => [
                Bootstrapper::OPTION_SCHEMA_UPDATE => true,
                Bootstrapper::OPTION_SHUTDOWN_KERNEL => true,
            ],
        ], $resolved);
    }

    public function testResolvesItsOwnOptionsAlongsideHookOptions(): void
    {
        $hooksExecutor = $this->createMock(HooksExecutorInterface::class);
        $hooksExecutor->method('configureOptions')->willReturnCallback(static function (OptionsResolver $resolver): void {
            $resolver->define('some.hook.id')->default([])->allowedTypes('array');
        });

        $resolved = $this->resolveOptions($hooksExecutor, [
            Bootstrapper::class => [
                Bootstrapper::OPTION_SCHEMA_UPDATE => false,
                Bootstrapper::OPTION_SHUTDOWN_KERNEL => false,
            ],
            'some.hook.id' => ['enabled' => false],
        ]);

        self::assertSame([
            Bootstrapper::class => [
                Bootstrapper::OPTION_SCHEMA_UPDATE => false,
                Bootstrapper::OPTION_SHUTDOWN_KERNEL => false,
            ],
            'some.hook.id' => ['enabled' => false],
        ], $resolved);
    }

    public function testRejectsAnUnrecognizedTopLevelKey(): void
    {
        $this->expectException(UndefinedOptionsException::class);

        $this->resolveOptions($this->hooksExecutorDefiningNothingExtra(), ['some.unknown.key' => []]);
    }

    public function testRejectsAnUnrecognizedOptionUnderItsOwnKey(): void
    {
        $this->expectException(UndefinedOptionsException::class);

        $this->resolveOptions($this->hooksExecutorDefiningNothingExtra(), [
            Bootstrapper::class => ['some_unknown_option' => true],
        ]);
    }

    public function testGetSqliteFilePathStripsExactlyOneLeadingSlash(): void
    {
        self::assertSame('path/to/file.db', $this->getSqliteFilePath('sqlite://i@i/path/to/file.db'));
    }

    public function testGetSqliteFilePathKeepsAnAbsolutePathAbsolute(): void
    {
        self::assertSame('/abs/path/to/file.db', $this->getSqliteFilePath('sqlite://i@i//abs/path/to/file.db'));
    }

    public function testGetSqliteFilePathReturnsNullForMemory(): void
    {
        self::assertNull($this->getSqliteFilePath('sqlite://:memory:'));
    }

    /**
     * @dataProvider unparseableSqliteDsnProvider
     */
    public function testGetSqliteFilePathThrowsForDsnFormsParseUrlCannotParse(string $databaseUrl): void
    {
        $this->expectException(LogicException::class);

        $this->getSqliteFilePath($databaseUrl);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unparseableSqliteDsnProvider(): iterable
    {
        yield 'triple slash' => ['sqlite:///path/to/file.db'];
        yield 'quadruple slash' => ['sqlite:////abs/path/to/file.db'];
    }

    public function testRunCommandDoesNothingOnSuccess(): void
    {
        $application = $this->createMock(Application::class);
        $application->method('run')->willReturn(0);

        $this->runCommand($application, ['command' => 'doctrine:database:create']);

        $this->expectNotToPerformAssertions();
    }

    public function testRunCommandThrowsNamingTheCommandAndExitCodeOnFailure(): void
    {
        $application = $this->createMock(Application::class);
        $application->method('run')->willReturn(2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Command "doctrine:database:create" failed with exit code 2.');

        $this->runCommand($application, ['command' => 'doctrine:database:create']);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(HooksExecutorInterface $hooksExecutor, array $options): array
    {
        return $this->callPrivateStatic('resolveOptions', $hooksExecutor, $options);
    }

    private function getSqliteFilePath(string $databaseUrl): ?string
    {
        return $this->callPrivateStatic('getSqliteFilePath', $databaseUrl);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function runCommand(Application $application, array $parameters): void
    {
        $this->callPrivateStatic('runCommand', $application, $parameters);
    }

    /**
     * @param mixed ...$arguments
     *
     * @return mixed
     */
    private function callPrivateStatic(string $method, ...$arguments)
    {
        $method = new ReflectionMethod(Bootstrapper::class, $method);
        $method->setAccessible(true);

        return $method->invoke(null, ...$arguments);
    }

    private function hooksExecutorDefiningNothingExtra(): HooksExecutorInterface
    {
        $hooksExecutor = $this->createMock(HooksExecutorInterface::class);
        $hooksExecutor->method('configureOptions')->willReturnCallback(static function (OptionsResolver $resolver): void {
        });

        return $hooksExecutor;
    }
}
