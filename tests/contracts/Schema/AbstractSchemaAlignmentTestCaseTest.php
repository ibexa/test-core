<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Schema;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDb1027Platform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\PostgreSQL100Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Ibexa\Contracts\Test\Core\Schema\AbstractSchemaAlignmentTestCase;
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
        self::assertSame([], AbstractSchemaAlignmentTestCase::compareSchemas($database, self::createSchema(), $platform));
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
            AbstractSchemaAlignmentTestCase::compareSchemas($database, $declared, $platform)
        );
    }

    public function testComparesPrefixLengthsWhereTheDatabaseStoresThem(): void
    {
        self::assertNotSame(
            [],
            AbstractSchemaAlignmentTestCase::compareSchemas(self::createSchema([100]), self::createSchema(), new MySQL80Platform())
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

    private static function withMigrationsTable(Schema $schema): Schema
    {
        $table = $schema->createTable('doctrine_migration_versions');
        $table->addColumn('version', 'string', ['length' => 191]);
        $table->setPrimaryKey(['version']);

        return $schema;
    }
}
