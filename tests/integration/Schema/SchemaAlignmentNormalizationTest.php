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
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;

/**
 * Reads back the tables _fixtures/schema.yaml declares for each case AbstractSchemaAlignmentTestCase
 * normalizes. The suite's bootstrap installed them, and {@see SchemaAlignmentTest} checks that they
 * compare as declared.
 *
 * @group integration
 *
 * @coversNothing
 */
final class SchemaAlignmentNormalizationTest extends IbexaKernelTestCase
{
    /**
     * Shows where each rule is needed. When this fails, DBAL or the database reads the table back
     * differently than it used to, so the rule may have become unnecessary, or needed elsewhere.
     *
     * @dataProvider provideCases
     *
     * @param string[] $platformsReadingItBackDifferently
     */
    public function testReadsItBackDifferentlyOnlyOnExpectedPlatforms(
        string $tableName,
        array $platformsReadingItBackDifferently
    ): void {
        $ibexaTestCore = $this->getIbexaTestCore();
        $connection = $ibexaTestCore->getDoctrineConnection();
        $platform = $connection->getDatabasePlatform();
        $schemaManager = $connection->getSchemaManager();

        $declared = $ibexaTestCore->getServiceByClassName(SchemaBuilderInterface::class)->buildSchema();
        $sequences = $platform->supportsSequences()
            ? array_filter(
                $schemaManager->listSequences(),
                static fn (Sequence $sequence): bool => str_starts_with($sequence->getName(), $tableName . '_')
            )
            : [];
        $database = new Schema([$schemaManager->listTableDetails($tableName)], $sequences);

        $statements = (new Comparator())->compare($database, new Schema([$declared->getTable($tableName)]))
            ->toSql($platform);

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
     * @return iterable<string, array{string, string[]}>
     */
    public static function provideCases(): iterable
    {
        yield 'autoincrement on a composite primary key' => ['alignment_composite_key', ['postgresql', 'sqlite']];
        yield 'INTEGER primary key without autoincrement' => ['alignment_integer_key', ['sqlite']];
        yield 'index prefix length' => ['alignment_prefix_length', ['mysql', 'postgresql', 'sqlite']];
        yield 'string longer than VARCHAR allows' => ['alignment_long_string', ['sqlite']];
    }
}
