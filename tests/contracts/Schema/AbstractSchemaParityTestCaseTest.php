<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Schema;

use Ibexa\Contracts\Test\Core\Schema\AbstractSchemaParityTestCase;
use Ibexa\Test\Core\Schema\Scenario;
use Ibexa\Tests\Contracts\Test\Core\Schema\Stub\SchemaParityTestStub;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\TestResult;

/**
 * @covers \Ibexa\Contracts\Test\Core\Schema\AbstractSchemaParityTestCase
 */
final class AbstractSchemaParityTestCaseTest extends TestCase
{
    public function testScenariosComeFromTheConcreteClass(): void
    {
        $ids = [];
        foreach (SchemaParityTestStub::provideScenarios() as $id => $arguments) {
            self::assertSame($id, $arguments[0]->getId());
            $ids[] = $id;
        }

        self::assertSame(
            [
                'fresh-install',
                'current-legacy-install',
                'package-added-to-legacy-install',
                'upgrade-from-v4.6.0..v4.6.3',
                'upgrade-from-v4.6.4',
            ],
            $ids
        );
    }

    public function testIsSkippedOutsideItsOwnPhpunitConfig(): void
    {
        $previous = $_ENV[AbstractSchemaParityTestCase::ENV_ENABLED] ?? null;
        unset($_ENV[AbstractSchemaParityTestCase::ENV_ENABLED]);
        putenv(AbstractSchemaParityTestCase::ENV_ENABLED);

        try {
            $result = new TestResult();
            (new SchemaParityTestStub('testReleasedSchemasAreAvailable'))->run($result);
            (new SchemaParityTestStub('testScenarioMatchesTheSchemaPath', [Scenario::freshInstall()]))->run($result);
        } finally {
            if ($previous !== null) {
                $_ENV[AbstractSchemaParityTestCase::ENV_ENABLED] = $previous;
            }
        }

        self::assertSame(2, $result->skippedCount());
        self::assertSame(0, $result->errorCount() + $result->failureCount());
    }
}
