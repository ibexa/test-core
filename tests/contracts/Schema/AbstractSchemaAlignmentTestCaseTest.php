<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\DoctrineSchema\Importer\SchemaImporter;
use Ibexa\Tests\Contracts\Test\Core\Schema\Stub\SchemaAlignmentTestCaseStub;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Contracts\Test\Core\Schema\AbstractSchemaAlignmentTestCase
 */
final class AbstractSchemaAlignmentTestCaseTest extends TestCase
{
    private const SCHEMA_FILE = __DIR__ . '/../../integration/Schema/_fixtures/schema.yaml';

    /**
     * @dataProvider provideDatabasesReadBackDifferently
     */
    public function testIgnoresWhatTheDatabaseReadsBackDifferently(AbstractPlatform $platform, Schema $database): void
    {
        self::assertSame([], $this->getReportedStatements($database, self::importSchema(), $platform));
    }

    /**
     * @dataProvider provideDatabasesReadBackDifferently
     */
    public function testReportsAMissingIndex(AbstractPlatform $platform, Schema $database): void
    {
        $declared = self::importSchema();
        $declared->getTable('alignment_long_string')->addIndex(['title'], 'alignment_long_string_title');

        self::assertContains(
            'CREATE INDEX alignment_long_string_title ON alignment_long_string (title)',
            $this->getReportedStatements($database, $declared, $platform)
        );
    }

    public function testComparesPrefixLengthsWhereTheDatabaseStoresThem(): void
    {
        $database = self::createDatabaseSchema(static function (Schema $schema): void {
            self::setIndexLengths($schema->getTable('alignment_prefix_length'), 'alignment_prefix_length_name', [100]);
        });

        self::assertSame(
            [
                'DROP INDEX alignment_prefix_length_name ON alignment_prefix_length',
                'CREATE INDEX alignment_prefix_length_name ON alignment_prefix_length (name(191))',
            ],
            $this->getReportedStatements($database, self::importSchema(), new MySQL80Platform())
        );
    }

    public function testStillComparesAStringWithinThePlatformLimit(): void
    {
        $database = self::createDatabaseSchema(static function (Schema $schema): void {
            self::readBackAsText($schema->getTable('alignment_long_string')->getColumn('title'));
        });

        self::assertContains(
            'CREATE TABLE alignment_long_string (id INTEGER NOT NULL, description CLOB NOT NULL, '
            . 'title VARCHAR(255) NOT NULL, PRIMARY KEY(id))',
            $this->getReportedStatements($database, self::importSchema(), new SqlitePlatform())
        );
    }

    /**
     * Each database schema is the declared one as that platform reads it back.
     *
     * @return iterable<string, array{AbstractPlatform, Schema}>
     */
    public static function provideDatabasesReadBackDifferently(): iterable
    {
        $mysql = static function (Schema $schema): void {
            self::setIndexLengths($schema->getTable('alignment_prefix_length'), 'alignment_prefix_length_name', [191]);
        };
        yield 'MySQL' => [new MySQL80Platform(), self::createDatabaseSchema($mysql)];
        yield 'MariaDB' => [new MariaDb1027Platform(), self::createDatabaseSchema($mysql)];

        $postgresql = static function (Schema $schema): void {
            self::setIndexLengths($schema->getTable('alignment_prefix_length'), 'alignment_prefix_length_name', [null]);
            $schema->createSequence('alignment_composite_key_id_seq');
        };
        yield 'PostgreSQL' => [new PostgreSQL100Platform(), self::createDatabaseSchema($postgresql)];

        $sqlite = static function (Schema $schema): void {
            self::setIndexLengths($schema->getTable('alignment_prefix_length'), 'alignment_prefix_length_name', [null]);
            $schema->getTable('alignment_composite_key')->getColumn('id')->setAutoincrement(false);
            $schema->getTable('alignment_integer_key')->getColumn('id')->setAutoincrement(true);
            self::readBackAsText($schema->getTable('alignment_long_string')->getColumn('description'));
        };
        yield 'SQLite' => [new SqlitePlatform(), self::createDatabaseSchema($sqlite)];
    }

    /**
     * Runs the test case on a database with the given schema.
     *
     * @return string[]
     */
    private function getReportedStatements(Schema $database, Schema $declared, AbstractPlatform $platform): array
    {
        $schemaManager = $this->createStub(AbstractSchemaManager::class);
        $schemaManager->method('createSchema')->willReturn($database);

        $connection = $this->createStub(Connection::class);
        $connection->method('getSchemaManager')->willReturn($schemaManager);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $schemaBuilder = $this->createStub(SchemaBuilderInterface::class);
        $schemaBuilder->method('buildSchema')->willReturn($declared);

        return (new SchemaAlignmentTestCaseStub($connection, $schemaBuilder))->getReportedStatements();
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
     * @param callable(Schema): void $readBack
     */
    private static function createDatabaseSchema(callable $readBack): Schema
    {
        $schema = self::importSchema();
        $readBack($schema);

        $table = $schema->createTable('doctrine_migration_versions');
        $table->addColumn('version', Types::STRING, ['length' => 191]);
        $table->setPrimaryKey(['version']);

        return $schema;
    }

    /**
     * @param array<int|null> $lengths
     */
    private static function setIndexLengths(Table $table, string $indexName, array $lengths): void
    {
        $index = $table->getIndex($indexName);
        $table->dropIndex($indexName);
        $table->addIndex(
            $index->getColumns(),
            $indexName,
            $index->getFlags(),
            ['lengths' => $lengths] + $index->getOptions()
        );
    }

    private static function readBackAsText(Column $column): void
    {
        $column->setType(Type::getType(Types::TEXT))->setLength(null);
    }
}
