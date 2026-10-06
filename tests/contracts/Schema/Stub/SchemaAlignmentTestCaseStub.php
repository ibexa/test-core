<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Schema\Stub;

use Doctrine\DBAL\Connection;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\Test\Core\Schema\AbstractSchemaAlignmentTestCase;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Runs the test case on a given connection and schema builder, through its override points.
 */
final class SchemaAlignmentTestCaseStub extends AbstractSchemaAlignmentTestCase
{
    private Connection $connection;

    private SchemaBuilderInterface $schemaBuilder;

    public function __construct(Connection $connection, SchemaBuilderInterface $schemaBuilder)
    {
        parent::__construct('testDatabaseMatchesTheSchemaBuilderEventSchema');

        $this->connection = $connection;
        $this->schemaBuilder = $schemaBuilder;
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
}
