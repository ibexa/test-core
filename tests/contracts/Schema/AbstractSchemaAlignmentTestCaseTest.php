<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnEditor;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaEditor;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableEditor;
use Doctrine\DBAL\Types\Types;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\Test\Core\Schema\AbstractSchemaAlignmentTestCase;
use Ibexa\DoctrineSchema\Importer\SchemaImporter;
use Ibexa\Tests\Contracts\Test\Core\Schema\Stub\SchemaAlignmentTestCaseStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractSchemaAlignmentTestCase::class)]
final class AbstractSchemaAlignmentTestCaseTest extends TestCase
{
    private const SCHEMA_FILE = __DIR__ . '/../../integration/Schema/_fixtures/schema.yaml';

    #[DataProvider('provideDatabasesReadBackDifferently')]
    public function testIgnoresWhatTheDatabaseReadsBackDifferently(
        AbstractPlatform $platform,
        Schema $database
    ): void {
        self::assertSame([], $this->getReportedStatements($database, self::importSchema(), $platform));
    }

    #[DataProvider('provideDatabasesReadBackDifferently')]
    public function testReportsAMissingIndex(
        AbstractPlatform $platform,
        Schema $database
    ): void {
        $declared = self::importSchema()->edit()->modifyTableByUnquotedName(
            'alignment_long_string',
            static function (TableEditor $table): void {
                $table->addIndex(
                    Index::editor()->setUnquotedName('alignment_long_string_title')->setUnquotedColumnNames('title')
                );
            }
        )->create();

        self::assertContains(
            'CREATE INDEX alignment_long_string_title ON alignment_long_string (title)',
            $this->getReportedStatements($database, $declared, $platform)
        );
    }

    public function testComparesPrefixLengthsWhereTheDatabaseStoresThem(): void
    {
        $database = self::createDatabaseSchema(static function (SchemaEditor $schema): void {
            self::setPrefixLength($schema, 100);
        });

        self::assertSame(
            [
                'ALTER TABLE alignment_prefix_length DROP INDEX alignment_prefix_length_name, '
                . 'ADD INDEX alignment_prefix_length_name (name(191))',
            ],
            $this->getReportedStatements($database, self::importSchema(), new MySQL80Platform())
        );
    }

    /**
     * Each database schema is the declared one as that platform reads it back.
     *
     * @return iterable<string, array{AbstractPlatform, Schema}>
     */
    public static function provideDatabasesReadBackDifferently(): iterable
    {
        $mysql = static function (SchemaEditor $schema): void {
            self::setPrefixLength($schema, 191);
        };
        yield 'MySQL' => [new MySQL80Platform(), self::createDatabaseSchema($mysql)];
        yield 'MariaDB' => [new MariaDBPlatform(), self::createDatabaseSchema($mysql)];

        $postgresql = static function (SchemaEditor $schema): void {
            self::setPrefixLength($schema, null);
            $schema->addSequence(Sequence::editor()->setUnquotedName('alignment_composite_key_id_seq')->create());
        };
        yield 'PostgreSQL' => [new PostgreSQLPlatform(), self::createDatabaseSchema($postgresql)];

        $sqlite = static function (SchemaEditor $schema): void {
            self::setPrefixLength($schema, null);
            self::setIdAutoincrement($schema, 'alignment_composite_key', false);
            self::setIdAutoincrement($schema, 'alignment_integer_key', true);
        };
        yield 'SQLite' => [new SQLitePlatform(), self::createDatabaseSchema($sqlite)];
    }

    /**
     * Runs the test case on a database with the given schema.
     *
     * @return string[]
     */
    private function getReportedStatements(
        Schema $database,
        Schema $declared,
        AbstractPlatform $platform
    ): array {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager->method('introspectSchema')->willReturn($database);
        $schemaManager->method('createComparator')->willReturnCallback(
            static fn (ComparatorConfig $config): Comparator => new Comparator($platform, $config)
        );

        $connection = $this->createStub(Connection::class);
        $connection->method('createSchemaManager')->willReturn($schemaManager);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $schemaBuilder = $this->createStub(SchemaBuilderInterface::class);
        $schemaBuilder->method('buildSchema')->willReturn($declared);

        return SchemaAlignmentTestCaseStub::create($connection, $schemaBuilder)->getReportedStatements();
    }

    /**
     * The schema the fixture declares, imported the way SchemaBuilderEvent subscribers do.
     */
    private static function importSchema(): Schema
    {
        return (new SchemaImporter())->importFromFile(self::SCHEMA_FILE);
    }

    /**
     * The declared schema with what the database reads back differently, plus the Doctrine
     * Migrations versioning table.
     *
     * @param callable(SchemaEditor): void $readBack
     */
    private static function createDatabaseSchema(callable $readBack): Schema
    {
        $schema = self::importSchema()->edit();
        $readBack($schema);

        return $schema
            ->addTable(
                Table::editor()
                    ->setUnquotedName('doctrine_migration_versions')
                    ->setColumns(
                        Column::editor()
                            ->setUnquotedName('version')
                            ->setTypeName(Types::STRING)
                            ->setLength(191)
                            ->create()
                    )
                    ->setPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('version')->create())
                    ->create()
            )
            ->create();
    }

    /**
     * Sets the prefix length the database reads alignment_prefix_length_name back with.
     *
     * @param positive-int|null $length
     */
    private static function setPrefixLength(
        SchemaEditor $schema,
        ?int $length
    ): void {
        $schema->modifyTableByUnquotedName(
            'alignment_prefix_length',
            static function (TableEditor $table) use ($length): void {
                $table
                    ->dropIndexByUnquotedName('alignment_prefix_length_name')
                    ->addIndex(
                        Index::editor()
                            ->setUnquotedName('alignment_prefix_length_name')
                            ->addUnquotedColumnName('name', $length)
                    );
            }
        );
    }

    /**
     * @param non-empty-string $tableName
     */
    private static function setIdAutoincrement(
        SchemaEditor $schema,
        string $tableName,
        bool $autoincrement
    ): void {
        $schema->modifyTableByUnquotedName(
            $tableName,
            static function (TableEditor $table) use ($autoincrement): void {
                $table->modifyColumnByUnquotedName(
                    'id',
                    static function (ColumnEditor $column) use ($autoincrement): void {
                        $column->setAutoincrement($autoincrement);
                    }
                );
            }
        );
    }
}
