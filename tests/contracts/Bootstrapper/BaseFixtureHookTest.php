<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Ibexa\Contracts\Core\Test\Persistence\Fixture\FixtureImporter;
use Ibexa\Contracts\Test\Core\Bootstrapper\BaseFixtureHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\DatabaseSchemaHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\DefaultFixtureProvider;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureHook;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\BaseFixtureHook
 */
final class BaseFixtureHookTest extends TestCase
{
    public function testLoadBaseFixtureOptionDefaultsToTrue(): void
    {
        $hook = new BaseFixtureHook(
            new DefaultFixtureProvider(),
            new FixtureImporter($this->createMock(Connection::class))
        );

        $options = $this->resolve($hook, []);

        self::assertTrue($options[BaseFixtureHook::OPTION_LOAD_BASE_FIXTURE]);
    }

    public function testRunsBeforeTheFixtureHookAndAfterTheSchemaHooks(): void
    {
        self::assertGreaterThan(FixtureHook::PRIORITY, BaseFixtureHook::PRIORITY);
        self::assertLessThan(DatabaseSchemaHook::PRIORITY, BaseFixtureHook::PRIORITY);
    }

    public function testImportsTheBaselineByDefault(): void
    {
        $inserted = [];
        $connection = $this->connectionRecordingInserts($inserted);

        $hook = new BaseFixtureHook(new DefaultFixtureProvider(), new FixtureImporter($connection));
        $hook($this->resolve($hook, []));

        self::assertNotEmpty($inserted, 'Expected the baseline fixture to be imported');
        self::assertContains('ezcontentobject', $inserted);
    }

    public function testDoesNotTouchTheDatabaseWhenDisabled(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::never())->method('insert');
        $connection->expects(self::never())->method('executeUpdate');
        $connection->expects(self::never())->method('executeStatement');

        $hook = new BaseFixtureHook(new DefaultFixtureProvider(), new FixtureImporter($connection));
        $hook($this->resolve($hook, [BaseFixtureHook::OPTION_LOAD_BASE_FIXTURE => false]));
    }

    public function testRejectsUnknownOptions(): void
    {
        $hook = new BaseFixtureHook(
            new DefaultFixtureProvider(),
            new FixtureImporter($this->createMock(Connection::class))
        );

        $this->expectException(UndefinedOptionsException::class);

        $this->resolve($hook, ['skip_base_fixture' => true]);
    }

    /**
     * @param list<string> $inserted receives the tables, in order, an insert was issued for
     *
     * @return \Doctrine\DBAL\Connection&\PHPUnit\Framework\MockObject\MockObject
     */
    private function connectionRecordingInserts(array &$inserted): Connection
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('supportsSequences')->willReturn(false);
        $platform->method('getTruncateTableSQL')->willReturn('TRUNCATE');

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform);
        $connection->method('quoteIdentifier')->willReturnArgument(0);
        $connection->method('insert')->willReturnCallback(
            static function (string $table) use (&$inserted): int {
                $inserted[] = $table;

                return 1;
            }
        );

        return $connection;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolve(BaseFixtureHook $hook, array $options): array
    {
        $resolver = new OptionsResolver();
        $hook->configureOptions($resolver);

        return $resolver->resolve($options);
    }
}
