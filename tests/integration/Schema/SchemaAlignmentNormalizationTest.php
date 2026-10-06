<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Test\Core\Schema;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use Ibexa\Tests\Contracts\Test\Core\Schema\Stub\SchemaAlignmentTestCaseStub;

/**
 * Creates each case AbstractSchemaAlignmentTestCase normalizes in the database the suite runs on, and
 * reads it back.
 *
 * @group integration
 *
 * @covers \Ibexa\Contracts\Test\Core\Schema\AbstractSchemaAlignmentTestCase
 */
final class SchemaAlignmentNormalizationTest extends IbexaKernelTestCase
{
    private ?string $createdTable = null;

    protected function tearDown(): void
    {
        if ($this->createdTable !== null) {
            $this->getIbexaTestCore()->getDoctrineConnection()->getSchemaManager()->dropTable($this->createdTable);
        }

        parent::tearDown();
    }

    /**
     * The test case passes with the table in the database, and next to the kernel's tables in the
     * declared schema.
     *
     * @dataProvider provideCases
     *
     * @param string[] $platformsReadingItBackDifferently
     */
    public function testComparesItAsDeclared(Table $table, array $platformsReadingItBackDifferently): void
    {
        $this->createTable($table);

        $kernelSchemaBuilder = $this->getIbexaTestCore()->getServiceByClassName(SchemaBuilderInterface::class);
        $tables = $kernelSchemaBuilder->buildSchema()->getTables();
        $tables[] = $table;
        $schemaBuilder = $this->createStub(SchemaBuilderInterface::class);
        $schemaBuilder->method('buildSchema')->willReturn(new Schema($tables));

        $testCase = new SchemaAlignmentTestCaseStub($this->getIbexaTestCore()->getDoctrineConnection(), $schemaBuilder);

        self::assertSame([], $testCase->getReportedStatements());
    }

    /**
     * Shows where each rule is needed. When this fails, DBAL or the database reads the case back
     * differently than it used to, so the rule may have become unnecessary, or needed elsewhere.
     *
     * @dataProvider provideCases
     *
     * @param string[] $platformsReadingItBackDifferently
     */
    public function testReadsItBackDifferentlyOnlyOnExpectedPlatforms(
        Table $table,
        array $platformsReadingItBackDifferently
    ): void {
        $this->createTable($table);

        $connection = $this->getIbexaTestCore()->getDoctrineConnection();
        $platform = $connection->getDatabasePlatform();
        $schemaManager = $connection->getSchemaManager();
        $sequences = $platform->supportsSequences()
            ? array_filter(
                $schemaManager->listSequences(),
                static fn (Sequence $sequence): bool => str_starts_with($sequence->getName(), $table->getName() . '_')
            )
            : [];
        $database = new Schema([$schemaManager->listTableDetails($table->getName())], $sequences);

        $statements = (new Comparator())->compare($database, new Schema([$table]))->toSql($platform);

        self::assertSame(
            in_array($platform->getName(), $platformsReadingItBackDifferently, true),
            $statements !== [],
            sprintf(
                "Statements the plain comparison gives on %s:\n%s",
                $platform->getName(),
                implode(";\n", $statements)
            )
        );
    }

    /**
     * @return iterable<string, array{Table, string[]}>
     */
    public static function provideCases(): iterable
    {
        $table = new Table('alignment_composite_key');
        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $table->addColumn('version', Types::INTEGER);
        $table->setPrimaryKey(['id', 'version']);
        yield 'autoincrement on a composite primary key' => [$table, ['postgresql', 'sqlite']];

        $table = new Table('alignment_integer_key');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);
        yield 'INTEGER primary key without autoincrement' => [$table, ['sqlite']];

        $table = new Table('alignment_prefix_length');
        $table->addColumn('name', Types::STRING, ['length' => 255]);
        // schema.yaml gives prefix lengths as strings
        $table->addIndex(['name'], 'alignment_prefix_length_name', [], ['lengths' => ['191']]);
        yield 'index prefix length' => [$table, ['mysql', 'postgresql', 'sqlite']];

        $table = new Table('alignment_long_string');
        $table->addColumn('description', Types::STRING, ['length' => 5000]);
        yield 'string longer than VARCHAR allows' => [$table, ['sqlite']];
    }

    private function createTable(Table $table): void
    {
        $connection = $this->getIbexaTestCore()->getDoctrineConnection();

        // Created from a clone: doctrine-schema's SqliteDbPlatform drops the autoincrement from a
        // composite key in the table it's given, which would hide the difference.
        foreach ((clone new Schema([$table]))->toSql($connection->getDatabasePlatform()) as $statement) {
            $connection->executeStatement($statement);
        }
        $this->createdTable = $table->getName();
    }
}
