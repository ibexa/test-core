<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Schema\Stub;

use Doctrine\DBAL\Connection;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\DoctrineSchema\SchemaAssetsFilterBypassInterface;
use Ibexa\Contracts\Test\Core\Schema\AbstractSchemaAlignmentTestCase;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Runs the test case on a given connection and schema builder, through its override points.
 */
final class SchemaAlignmentTestCaseStub extends AbstractSchemaAlignmentTestCase
{
    private Connection $connection;

    private SchemaBuilderInterface $schemaBuilder;

    /**
     * A named constructor, as TestCase's own constructor can't be overridden.
     */
    public static function create(
        Connection $connection,
        SchemaBuilderInterface $schemaBuilder
    ): self {
        $testCase = new self('testDatabaseMatchesTheSchemaBuilderEventSchema');
        $testCase->connection = $connection;
        $testCase->schemaBuilder = $schemaBuilder;

        return $testCase;
    }

    /**
     * @return string[] the statements the test reports when it fails; none when it passes
     */
    public function getReportedStatements(): array
    {
        try {
            $this->testDatabaseMatchesTheSchemaBuilderEventSchema();
        } catch (ExpectationFailedException $e) {
            $failure = $e->getComparisonFailure();
            assert($failure !== null);

            /** @var string[] $statements */
            $statements = $failure->getActual();

            return $statements;
        }

        return [];
    }

    protected function getDatabaseConnection(): Connection
    {
        return $this->connection;
    }

    protected function getSchemaBuilder(): SchemaBuilderInterface
    {
        return $this->schemaBuilder;
    }

    protected function getSchemaAssetsFilterBypass(): SchemaAssetsFilterBypassInterface
    {
        // The stubbed connection has no schema assets filter to lift
        return new class() implements SchemaAssetsFilterBypassInterface {
            public function call(
                Connection $connection,
                callable $callback
            ): mixed {
                return $callback();
            }
        };
    }
}
