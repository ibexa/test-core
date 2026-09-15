<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Exception\TypesException;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\DoctrineSchema\DbPlatformFactoryInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\DatabaseSchemaHook;
use Ibexa\DoctrineSchema\Database\DbPlatform\SqliteDbPlatform;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\DatabaseSchemaHook
 */
final class DatabaseSchemaHookTest extends TestCase
{
    /** @var list<string> */
    private array $executedStatements = [];

    protected function setUp(): void
    {
        $this->executedStatements = [];
    }

    /**
     * Vanilla SQLitePlatform drops the table-level PRIMARY KEY clause once a table has an
     * autoincrement column, which silently turns a composite key into a single-column one. The
     * schema has to be generated with Ibexa's platform, not with whatever the connection carries.
     *
     * @throws \Exception
     * @throws TypesException
     */
    public function testGeneratesSchemaWithIbexaDatabasePlatform(): void
    {
        $hook = $this->createHook(new SqliteDbPlatform());

        $hook($this->resolve($hook, []));

        self::assertStringContainsString('PRIMARY KEY (id, version)', $this->getExecutedDdl());
    }

    /**
     * @throws \Exception
     * @throws TypesException
     */
    public function testFallsBackToConnectionPlatformWhenFactoryProvidesNone(): void
    {
        $hook = $this->createHook(null);

        $hook($this->resolve($hook, []));

        // the connection's own vanilla platform is used, PK degradation and all
        $ddl = $this->getExecutedDdl();
        self::assertStringContainsString('CREATE TABLE ibexa_content_field', $ddl);
        self::assertStringContainsString('id INTEGER PRIMARY KEY AUTOINCREMENT', $ddl);
        self::assertStringNotContainsString('PRIMARY KEY (id, version)', $ddl);
    }

    /**
     * A connection configured with driverClass rather than driver has no 'driver' key at all. That
     * must degrade to the connection's own platform, not blow up on a missing array key.
     *
     * @throws \Exception
     * @throws TypesException
     */
    public function testDegradesToConnectionPlatformWhenDriverParamIsAbsent(): void
    {
        $hook = $this->createHook(new SqliteDbPlatform(), []);

        $hook($this->resolve($hook, []));

        $ddl = $this->getExecutedDdl();
        self::assertStringContainsString('id INTEGER PRIMARY KEY AUTOINCREMENT', $ddl);
        self::assertStringNotContainsString('PRIMARY KEY (id, version)', $ddl);
    }

    /**
     * @throws TypesException
     */
    public function testLoadSchemaOptionDefaultsToTrue(): void
    {
        $hook = $this->createHook(new SqliteDbPlatform());

        $options = $this->resolve($hook, []);

        self::assertTrue($options[DatabaseSchemaHook::OPTION_LOAD_SCHEMA]);
    }

    /**
     * @throws \Exception
     */
    public function testDoesNotBuildSchemaWhenDisabled(): void
    {
        $schemaBuilder = $this->createMock(SchemaBuilderInterface::class);
        $schemaBuilder->expects(self::never())->method('buildSchema');

        $hook = new DatabaseSchemaHook(
            $schemaBuilder,
            $this->createConnection(),
            $this->createMock(DbPlatformFactoryInterface::class)
        );

        $hook($this->resolve($hook, [DatabaseSchemaHook::OPTION_LOAD_SCHEMA => false]));

        self::assertSame([], $this->executedStatements);
    }

    private function getExecutedDdl(): string
    {
        self::assertNotEmpty($this->executedStatements, 'the hook executed no statements at all');

        return implode("\n", $this->executedStatements);
    }

    /**
     * @param array<string, mixed> $connectionParams
     *
     * @throws TypesException
     */
    private function createHook(
        ?AbstractPlatform $ibexaPlatform,
        array $connectionParams = ['driver' => 'pdo_sqlite']
    ): DatabaseSchemaHook {
        $schemaBuilder = $this->createStub(SchemaBuilderInterface::class);
        $schemaBuilder->method('buildSchema')->willReturn($this->createSchema());

        // keyed like the real factory, so a driver it does not know about resolves to null
        $dbPlatformFactory = $this->createStub(DbPlatformFactoryInterface::class);
        $dbPlatformFactory->method('createDatabasePlatformFromDriverName')->willReturnCallback(
            static fn (string $driverName): ?AbstractPlatform => $driverName === 'pdo_sqlite'
                ? $ibexaPlatform
                : null
        );

        return new DatabaseSchemaHook(
            $schemaBuilder,
            $this->createConnection($connectionParams),
            $dbPlatformFactory
        );
    }

    /**
     * A connection reporting the vanilla SQLite platform, recording every statement it is asked
     * to run.
     *
     * @param array<string, mixed> $params
     */
    private function createConnection(array $params = ['driver' => 'pdo_sqlite']): Connection
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getParams')->willReturn($params);
        $connection->method('getDatabasePlatform')->willReturn(new SQLitePlatform());
        $connection->method('executeStatement')->willReturnCallback(
            function (string $sql): int {
                $this->executedStatements[] = $sql;

                return 0;
            }
        );

        return $connection;
    }

    /**
     * Mirrors ibexa_content_field: a composite primary key over an autoincrement column.
     *
     * @throws TypesException
     */
    private function createSchema(): Schema
    {
        $schema = new Schema();
        $table = $schema->createTable('ibexa_content_field');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('version', 'integer', ['default' => 0]);
        $table->addPrimaryKeyConstraint(
            PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id', 'version')->create()
        );

        return $schema;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolve(
        DatabaseSchemaHook $hook,
        array $options
    ): array {
        $resolver = new OptionsResolver();
        $hook->configureOptions($resolver);

        return $resolver->resolve($options);
    }
}
