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
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
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
 * Override {@see getDatabaseConnection()} or {@see getSchemaBuilder()} if the suite reaches its
 * database, or builds the schema it declares, another way.
 *
 * @experimental
 */
abstract class AbstractSchemaAlignmentTestCase extends IbexaKernelTestCase
{
    private const MIGRATIONS_TABLE = 'doctrine_migration_versions';

    final public function testDatabaseMatchesTheSchemaBuilderEventSchema(): void
    {
        $connection = $this->getDatabaseConnection();

        $statements = self::compareSchemas(
            $connection->getSchemaManager()->createSchema(),
            $this->getSchemaBuilder()->buildSchema(),
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
     * Returns the statements that would turn the database schema into the declared one, leaving out
     * what DBAL can't read back from a database the way it was declared.
     *
     * @return string[]
     */
    private static function compareSchemas(Schema $database, Schema $declared, AbstractPlatform $platform): array
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
        self::ignoreSequences($schema);

        foreach ($schema->getTables() as $table) {
            foreach ($table->getIndexes() as $index) {
                self::normalizeIndexPrefixLengths($table, $index, $platform);
            }

            foreach ($table->getColumns() as $column) {
                self::ignoreAutoincrementOnSqlite($column, $platform);
                self::treatOverlongStringAsText($column, $platform);
            }
        }
    }

    /**
     * Schemas don't declare sequences, as MySQL and MariaDB have none. The only ones a database has
     * are the sequences PostgreSQL creates for SERIAL columns, and DBAL ties those to their column
     * only on a single-column primary key, so ezcontentclass_id_seq would be reported. Matching them
     * by name isn't reliable either: PostgreSQL truncates the name to 63 characters. The columns'
     * autoincrement flag is still compared.
     */
    private static function ignoreSequences(Schema $schema): void
    {
        foreach ($schema->getSequences() as $sequence) {
            $schema->dropSequence($sequence->getName());
        }
    }

    /**
     * Only MySQL and MariaDB store index prefix lengths, so other platforms read them back as null.
     * On those two, schema.yaml gives them as strings, and the database as integers.
     */
    private static function normalizeIndexPrefixLengths(Table $table, Index $index, AbstractPlatform $platform): void
    {
        if ($index->isPrimary() || !$index->hasOption('lengths')) {
            return;
        }

        $lengths = $index->getOption('lengths');
        assert(is_array($lengths));

        $normalizedLengths = $platform instanceof MySqlPlatform
            ? array_map(static fn ($length): ?int => $length === null ? null : (int)$length, $lengths)
            : [];

        self::replaceIndexOptions($table, $index, ['lengths' => $normalizedLengths] + $index->getOptions());
    }

    /**
     * The autoincrement flag means nothing on SQLite, either way. SQLite can't express it on a
     * composite primary key, so ezcontentclass.id reads back without it. And DBAL reports every
     * single-column INTEGER PRIMARY KEY as autoincrement, so ezuser.contentobject_id reads back with
     * it. Leaving out composite keys alone would still report the latter.
     */
    private static function ignoreAutoincrementOnSqlite(Column $column, AbstractPlatform $platform): void
    {
        if (!$platform instanceof SqlitePlatform) {
            return;
        }

        $column->setAutoincrement(false);
    }

    /**
     * A string column longer than the platform's VARCHAR limit is created as a text one (a CLOB on
     * SQLite, where the limit is 4000), and read back as such.
     */
    private static function treatOverlongStringAsText(Column $column, AbstractPlatform $platform): void
    {
        $length = $column->getLength();
        if (
            !$column->getType() instanceof StringType
            || $length === null
            || $length <= $platform->getVarcharMaxLength()
        ) {
            return;
        }

        $column->setType(Type::getType(Types::TEXT));
        $column->setLength(null);
    }

    /**
     * An index's options can't be changed, so it's replaced by an index with the new ones.
     *
     * @param array<string, mixed> $options
     */
    private static function replaceIndexOptions(Table $table, Index $index, array $options): void
    {
        $table->dropIndex($index->getName());
        if ($index->isUnique()) {
            $table->addUniqueIndex($index->getColumns(), $index->getName(), $options);
        } else {
            $table->addIndex($index->getColumns(), $index->getName(), $index->getFlags(), $options);
        }
    }
}
