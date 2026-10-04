<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySqlPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;

/**
 * Checks that the database the integration suite runs on has the schema SchemaBuilderEvent declares.
 *
 * Integration suites run once with each install path: {@see \Ibexa\Contracts\Test\Core\Bootstrapper\DatabaseSchemaHook}
 * executes the SchemaBuilderEvent schema, ibexa/core's DoctrineMigrationsSchemaHook runs the Doctrine
 * migrations instead. On the latter, this test is what proves both paths create the same schema; on the
 * former, it passes by construction. Since a schema change always comes with a new migration, a fresh
 * install runs all of them, so this covers every migration.
 *
 * To use it, add an empty subclass to the integration suite:
 *
 * ```php
 * final class SchemaAlignmentTest extends AbstractSchemaAlignmentTestCase
 * {
 * }
 * ```
 *
 * @experimental
 */
abstract class AbstractSchemaAlignmentTestCase extends IbexaKernelTestCase
{
    private const MIGRATIONS_TABLE = 'doctrine_migration_versions';

    final public function testDatabaseMatchesTheSchemaBuilderEventSchema(): void
    {
        $container = self::getContainer();
        $connection = $container->get('doctrine.dbal.default_connection');
        $schemaBuilder = $container->get(SchemaBuilderInterface::class);
        self::assertInstanceOf(Connection::class, $connection);
        self::assertInstanceOf(SchemaBuilderInterface::class, $schemaBuilder);

        $statements = self::compareSchemas(
            $connection->getSchemaManager()->createSchema(),
            $schemaBuilder->buildSchema(),
            $connection->getDatabasePlatform()
        );

        self::assertSame([], $statements, sprintf(
            "The database doesn't match the schema SchemaBuilderEvent declares. If Doctrine Migrations "
            . "installed it, a migration is missing or incomplete. These statements would make it match:\n%s",
            implode(";\n", $statements)
        ));
    }

    /**
     * Returns the statements that would turn the database schema into the declared one, leaving out
     * what DBAL can't read back from a database the way it was declared.
     *
     * @internal public for this package's own tests
     *
     * @return string[]
     */
    final public static function compareSchemas(Schema $database, Schema $declared, AbstractPlatform $platform): array
    {
        $database = clone $database;
        $declared = clone $declared;
        if ($database->hasTable(self::MIGRATIONS_TABLE)) {
            $database->dropTable(self::MIGRATIONS_TABLE);
        }

        self::normalize($database, $platform);
        self::normalize($declared, $platform);

        return (new Comparator())->compare($database, $declared)->toSql($platform);
    }

    private static function normalize(Schema $schema, AbstractPlatform $platform): void
    {
        // PostgreSQL's SERIAL sequences on tables with a composite primary key aren't recognized as
        // implicit. The autoincrement flag of the columns still covers them.
        foreach ($schema->getSequences() as $sequence) {
            $schema->dropSequence($sequence->getName());
        }

        foreach ($schema->getTables() as $table) {
            foreach ($table->getIndexes() as $index) {
                if ($index->isPrimary() || !$index->hasOption('lengths')) {
                    continue;
                }

                // Only MySQL and MariaDB store index prefix lengths. schema.yaml gives them as strings,
                // the database as integers.
                $lengths = $platform instanceof MySqlPlatform
                    ? array_map(
                        static fn ($length): ?int => $length === null ? null : (int)$length,
                        (array)$index->getOption('lengths')
                    )
                    : [];
                $options = ['lengths' => $lengths] + $index->getOptions();

                $table->dropIndex($index->getName());
                if ($index->isUnique()) {
                    $table->addUniqueIndex($index->getColumns(), $index->getName(), $options);
                } else {
                    $table->addIndex($index->getColumns(), $index->getName(), $index->getFlags(), $options);
                }
            }

            // SQLite can't express autoincrement on a composite primary key, and reports every
            // INTEGER PRIMARY KEY column as autoincrement.
            if ($platform instanceof SqlitePlatform) {
                foreach ($table->getColumns() as $column) {
                    $column->setAutoincrement(false);
                }
            }
        }
    }
}
