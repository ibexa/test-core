<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Test\Core\Schema;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\ComparatorConfig;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Sequence;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\DoctrineSchema\SchemaAssetsFilterBypassInterface;
use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Reads back the tables _fixtures/schema.yaml declares for each case AbstractSchemaAlignmentTestCase
 * normalizes. The suite's bootstrap installed them, and {@see SchemaAlignmentTest} checks that they
 * compare as declared.
 */
#[Group('integration')]
#[CoversNothing]
final class SchemaAlignmentNormalizationTest extends IbexaKernelTestCase
{
    /**
     * Shows where each rule is needed. When this fails, DBAL or the database reads the table back
     * differently than it used to, so the rule may have become unnecessary, or needed elsewhere.
     *
     * @param string[] $platformsReadingItBackDifferently
     */
    #[DataProvider('provideCases')]
    public function testReadsItBackDifferentlyOnlyOnExpectedPlatforms(
        string $tableName,
        array $platformsReadingItBackDifferently
    ): void {
        $ibexaTestCore = $this->getIbexaTestCore();
        $connection = $ibexaTestCore->getDoctrineConnection();
        $platform = $connection->getDatabasePlatform();
        $schemaManager = $connection->createSchemaManager();

        $declared = $ibexaTestCore->getServiceByClassName(SchemaBuilderInterface::class)->buildSchema();
        $database = $ibexaTestCore->getServiceByClassName(SchemaAssetsFilterBypassInterface::class)->call(
            $connection,
            // Read the way AbstractSchemaAlignmentTestCase does: on SQLite, introspectTableByUnquotedName()
            // doesn't report a single-column INTEGER primary key as autoincrement, but introspectSchema() does
            static function () use ($schemaManager, $tableName): Schema {
                $database = $schemaManager->introspectSchema();
                $sequences = array_filter(
                    $database->getSequences(),
                    static fn (Sequence $sequence): bool => str_starts_with(
                        $sequence->getObjectName()->getUnqualifiedName()->getValue(),
                        $tableName . '_'
                    )
                );

                return Schema::editor()
                    ->setTables($database->getTable($tableName))
                    ->setSequences(...$sequences)
                    ->create();
            }
        );

        $diff = $schemaManager
            ->createComparator((new ComparatorConfig())->withReportModifiedIndexes(false))
            ->compareSchemas($database, Schema::editor()->setTables($declared->getTable($tableName))->create());

        $platformName = self::getPlatformName($platform);
        if (in_array($platformName, $platformsReadingItBackDifferently, true) !== !$diff->isEmpty()) {
            // Only generated to explain the failure: SQLite recreates a table to alter it, and DBAL deprecates
            // the autoincrement on a composite primary key that alignment_composite_key declares
            self::fail(sprintf(
                "Statements the plain comparison gives on %s:\n%s",
                $platformName,
                implode(";\n", $platform->getAlterSchemaSQL($diff))
            ));
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{string, string[]}>
     */
    public static function provideCases(): iterable
    {
        yield 'autoincrement on a composite primary key' => ['alignment_composite_key', ['sqlite']];
        yield 'INTEGER primary key without autoincrement' => ['alignment_integer_key', ['sqlite']];
        yield 'index prefix length' => ['alignment_prefix_length', ['mysql', 'postgresql', 'sqlite']];
        yield 'string longer than VARCHAR allows' => ['alignment_long_string', []];
    }

    private static function getPlatformName(AbstractPlatform $platform): string
    {
        return match (true) {
            $platform instanceof AbstractMySQLPlatform => 'mysql',
            $platform instanceof PostgreSQLPlatform => 'postgresql',
            $platform instanceof SQLitePlatform => 'sqlite',
            default => $platform::class,
        };
    }
}
