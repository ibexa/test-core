<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\TableEditor;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\DoctrineSchema\SchemaAssetsFilterBypassInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\DatabaseSchemaHook;
use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;

/**
 * Checks that the database the integration suite runs on has the schema SchemaBuilderEvent declares.
 *
 * Integration suites run once with each install path: {@see DatabaseSchemaHook}
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
 * Override {@see getDatabaseConnection()}, {@see getSchemaBuilder()} or {@see getSchemaAssetsFilterBypass()}
 * if the suite reaches its database, builds the schema it declares, or lifts the schema assets filter,
 * another way.
 *
 * @experimental
 */
abstract class AbstractSchemaAlignmentTestCase extends IbexaKernelTestCase
{
    private const MIGRATIONS_TABLE = 'doctrine_migration_versions';

    final public function testDatabaseMatchesTheSchemaBuilderEventSchema(): void
    {
        $connection = $this->getDatabaseConnection();
        $schemaManager = $connection->createSchemaManager();

        $statements = self::compareSchemas(
            $this->getSchemaAssetsFilterBypass()->call(
                $connection,
                static fn (): Schema => $schemaManager->introspectSchema()
            ),
            $this->getSchemaBuilder()->buildSchema(),
            $schemaManager->createComparator((new ComparatorConfig())->withReportModifiedIndexes(false)),
            $connection->getDatabasePlatform()
        );

        self::assertSame([], $statements, sprintf(
            "The database doesn't match the schema SchemaBuilderEvent declares. If Doctrine Migrations "
            . "installed it, a migration is missing or incomplete. These statements would make it match:\n%s",
            implode(";\n", $statements)
        ));
    }

    /**
     * The connection to the database the suite installs the schema in. Override it when that isn't
     * the kernel's default Doctrine connection.
     */
    protected function getDatabaseConnection(): Connection
    {
        return $this->getIbexaTestCore()->getDoctrineConnection();
    }

    /**
     * The schema builder whose schema the database is compared with. Override it when that isn't
     * the one the kernel exposes.
     */
    protected function getSchemaBuilder(): SchemaBuilderInterface
    {
        return $this->getIbexaTestCore()->getServiceByClassName(SchemaBuilderInterface::class);
    }

    /**
     * Lifts the schema assets filter while the database is read, as it hides the tables
     * SchemaBuilderEvent declares from introspection. Override it when that isn't the bypass the
     * kernel exposes.
     */
    protected function getSchemaAssetsFilterBypass(): SchemaAssetsFilterBypassInterface
    {
        return $this->getIbexaTestCore()->getServiceByClassName(SchemaAssetsFilterBypassInterface::class);
    }

    /**
     * Returns the statements that would turn the database schema into the declared one, leaving out
     * what DBAL can't read back from a database the way it was declared.
     *
     * @return string[]
     */
    private static function compareSchemas(
        Schema $database,
        Schema $declared,
        Comparator $comparator,
        AbstractPlatform $platform
    ): array {
        if ($database->hasTable(self::MIGRATIONS_TABLE)) {
            $database = $database->edit()->dropTableByUnquotedName(self::MIGRATIONS_TABLE)->create();
        }

        return $platform->getAlterSchemaSQL($comparator->compareSchemas(
            self::normalize($database, $platform),
            self::normalize($declared, $platform)
        ));
    }

    private static function normalize(
        Schema $schema,
        AbstractPlatform $platform
    ): Schema {
        $tables = [];
        foreach ($schema->getTables() as $table) {
            $tableEditor = $table->edit();

            foreach ($table->getIndexes() as $index) {
                self::normalizeIndexPrefixLengths($tableEditor, $index, $platform);
            }

            foreach ($table->getColumns() as $column) {
                self::ignoreAutoincrementOnSqlite($tableEditor, $column, $platform);
            }

            $tables[] = $tableEditor->create();
        }

        $schemaEditor = $schema->edit()->setTables(...$tables);
        self::ignoreSequences($schemaEditor);

        return $schemaEditor->create();
    }

    /**
     * Schemas don't declare sequences, as MySQL and MariaDB have none. The only ones a database has
     * are the sequences PostgreSQL creates for SERIAL columns, and DBAL ties those to their column
     * only on a single-column primary key, so ezcontentclass_id_seq would be reported. Matching them
     * by name isn't reliable either: PostgreSQL truncates the name to 63 characters. The columns'
     * autoincrement flag is still compared.
     */
    private static function ignoreSequences(SchemaEditor $schema): void
    {
        $schema->setSequences();
    }

    /**
     * Only MySQL and MariaDB store index prefix lengths, so other platforms read them back as null.
     * On those two, schema.yaml gives them as strings, and the database as integers, which the
     * comparison tells apart until the index is rebuilt from its indexed columns.
     */
    private static function normalizeIndexPrefixLengths(
        TableEditor $table,
        Index $index,
        AbstractPlatform $platform
    ): void {
        $columns = $index->getIndexedColumns();
        $hasLengths = array_filter($columns, static fn (IndexedColumn $column): bool => $column->getLength() !== null);
        if ($hasLengths === []) {
            return;
        }

        $keepsLengths = $platform instanceof AbstractMySQLPlatform;
        $normalizedColumns = array_map(
            static fn (IndexedColumn $column): IndexedColumn => new IndexedColumn(
                $column->getColumnName(),
                $keepsLengths ? $column->getLength() : null
            ),
            $columns
        );

        // An index can't be changed, so it's replaced by one with the normalized columns
        $table
            ->dropIndex($index->getObjectName())
            ->addIndex($index->edit()->setColumns(...$normalizedColumns));
    }

    /**
     * The autoincrement flag means nothing on SQLite, either way. SQLite can't express it on a
     * composite primary key, so ezcontentclass.id reads back without it. And DBAL reports every
     * single-column INTEGER PRIMARY KEY as autoincrement, so ezuser.contentobject_id reads back with
     * it. Leaving out composite keys alone would still report the latter.
     */
    private static function ignoreAutoincrementOnSqlite(
        TableEditor $table,
        Column $column,
        AbstractPlatform $platform
    ): void {
        if (!$platform instanceof SQLitePlatform) {
            return;
        }

        $table->modifyColumn(
            $column->getObjectName(),
            static function (ColumnEditor $column): void {
                $column->setAutoincrement(false);
            }
        );
    }
}
