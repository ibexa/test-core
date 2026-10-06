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
use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Tests\Contracts\Test\Core\Schema\Stub\SchemaAlignmentTestCaseStub;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Contracts\Test\Core\Schema\AbstractSchemaAlignmentTestCase
 */
final class AbstractSchemaAlignmentTestCaseTest extends TestCase
{
    /**
     * @dataProvider provideDatabasesReadBackDifferently
     */
    public function testIgnoresWhatTheDatabaseReadsBackDifferently(AbstractPlatform $platform, Schema $database): void
    {
        self::assertSame([], $this->getReportedStatements($database, self::createSchema(), $platform));
    }

    /**
     * @dataProvider provideDatabasesReadBackDifferently
     */
    public function testReportsAMissingIndex(AbstractPlatform $platform, Schema $database): void
    {
        $declared = self::createSchema();
        $declared->getTable('plain_table')->addIndex(['code'], 'plain_table_code');

        self::assertContains(
            'CREATE INDEX plain_table_code ON plain_table (code)',
            $this->getReportedStatements($database, $declared, $platform)
        );
    }

    public function testComparesPrefixLengthsWhereTheDatabaseStoresThem(): void
    {
        self::assertSame(
            [
                'DROP INDEX versioned_table_name ON versioned_table',
                'CREATE INDEX versioned_table_name ON versioned_table (name(191))',
            ],
            $this->getReportedStatements(self::createSchema([100]), self::createSchema(), new MySQL80Platform())
        );
    }

    public function testComparesAStringLongerThanThePlatformAllowsAsText(): void
    {
        self::assertSame([], $this->getReportedStatements(
            self::createDescriptionSchema('text', null),
            self::createDescriptionSchema('string', 10000),
            new SqlitePlatform()
        ));
    }

    public function testStillComparesAStringWithinThePlatformLimit(): void
    {
        self::assertContains(
            'CREATE TABLE translated_table (id INTEGER NOT NULL, description VARCHAR(1000) NOT NULL, PRIMARY KEY(id))',
            $this->getReportedStatements(
                self::createDescriptionSchema('text', null),
                self::createDescriptionSchema('string', 1000),
                new SqlitePlatform()
            )
        );
    }

    /**
     * Each database schema is the declared one as that platform reads it back, plus the Doctrine
     * Migrations versioning table.
     *
     * @return iterable<string, array{AbstractPlatform, Schema}>
     */
    public static function provideDatabasesReadBackDifferently(): iterable
    {
        yield 'MySQL' => [new MySQL80Platform(), self::withMigrationsTable(self::createSchema([191]))];
        yield 'MariaDB' => [new MariaDb1027Platform(), self::withMigrationsTable(self::createSchema([191]))];

        $postgreSql = self::withMigrationsTable(self::createSchema([null]));
        $postgreSql->createSequence('versioned_table_id_seq');
        yield 'PostgreSQL' => [new PostgreSQL100Platform(), $postgreSql];

        yield 'SQLite' => [new SqlitePlatform(), self::withMigrationsTable(self::createSchema([null], false, true))];
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
     * Defaults are what schema.yaml declares.
     *
     * @param array<int|string|null> $nameLengths
     */
    private static function createSchema(
        array $nameLengths = ['191'],
        bool $compositeKeyAutoincrement = true,
        bool $integerKeyAutoincrement = false
    ): Schema {
        $schema = new Schema();

        $versioned = $schema->createTable('versioned_table');
        $versioned->addColumn('id', 'integer', ['autoincrement' => $compositeKeyAutoincrement]);
        $versioned->addColumn('version', 'integer');
        $versioned->addColumn('name', 'string', ['length' => 255]);
        $versioned->setPrimaryKey(['id', 'version']);
        $versioned->addIndex(['name'], 'versioned_table_name', [], ['lengths' => $nameLengths]);

        $plain = $schema->createTable('plain_table');
        $plain->addColumn('id', 'integer', ['autoincrement' => $integerKeyAutoincrement]);
        $plain->addColumn('code', 'string', ['length' => 32]);
        $plain->setPrimaryKey(['id']);

        return $schema;
    }

    private static function createDescriptionSchema(string $type, ?int $length): Schema
    {
        $schema = new Schema();
        $table = $schema->createTable('translated_table');
        $table->addColumn('id', 'integer');
        $table->addColumn('description', $type, ['length' => $length]);
        $table->setPrimaryKey(['id']);

        return $schema;
    }

    private static function withMigrationsTable(Schema $schema): Schema
    {
        $table = $schema->createTable('doctrine_migration_versions');
        $table->addColumn('version', 'string', ['length' => 191]);
        $table->setPrimaryKey(['version']);

        return $schema;
    }
}
