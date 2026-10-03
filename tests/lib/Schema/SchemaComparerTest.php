<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Schema;

use Ibexa\Contracts\Test\Core\Schema\ExpectedDifference;
use Ibexa\Test\Core\Schema\SchemaComparer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Contracts\Test\Core\Schema\ExpectedDifference
 * @covers \Ibexa\Test\Core\Schema\SchemaComparer
 * @covers \Ibexa\Test\Core\Schema\TableDifference
 */
final class SchemaComparerTest extends TestCase
{
    public function testEqualTablesHaveNoDifferences(): void
    {
        $lines = ['`id` int NOT NULL AUTO_INCREMENT', 'PRIMARY KEY (`id`)'];

        self::assertSame([], SchemaComparer::compare(['t' => $lines], ['t' => $lines], self::nothingExpected()));
    }

    public function testReportsMissingAndUnexpectedLines(): void
    {
        $differences = SchemaComparer::compare(
            ['ezpage_pages' => ['KEY `ezpage_pages_source_page_id` (`source_page_id`)', 'PRIMARY KEY (`id`)']],
            ['ezpage_pages' => ['KEY `fk_ezpage_pages_source_page` (`source_page_id`)', 'PRIMARY KEY (`id`)']],
            self::nothingExpected()
        );

        self::assertCount(1, $differences);
        self::assertSame('ezpage_pages', $differences[0]->getTable());
        self::assertSame(['KEY `ezpage_pages_source_page_id` (`source_page_id`)'], $differences[0]->getMissingLines());
        self::assertSame(['KEY `fk_ezpage_pages_source_page` (`source_page_id`)'], $differences[0]->getUnexpectedLines());
    }

    public function testReportsMissingAndUnexpectedTables(): void
    {
        $differences = SchemaComparer::compare(
            ['kept' => ['a'], 'created' => ['b'], 'dropped' => null],
            ['kept' => ['a'], 'created' => null, 'dropped' => ['c']],
            self::nothingExpected()
        );

        self::assertCount(2, $differences);
        self::assertSame('created', $differences[0]->getTable());
        self::assertTrue($differences[0]->isTableMissing());
        self::assertSame('dropped', $differences[1]->getTable());
        self::assertTrue($differences[1]->isTableUnexpected());
    }

    public function testCountsDuplicateLines(): void
    {
        $differences = SchemaComparer::compare(['t' => ['x', 'x']], ['t' => ['x']], self::nothingExpected());

        self::assertSame(['x'], $differences[0]->getMissingLines());
    }

    public function testSkipsExpectedDifferences(): void
    {
        $expected = [
            ExpectedDifference::unexpected('ibexa_page', 'CONSTRAINT `fk_ezpage_pages_source_page` *')
                ->onPlatforms('mysql', 'mariadb')
                ->inScenarios('upgrade-from-*')
                ->because('the 5.0 rename keeps the 4.6 FK name'),
        ];
        $matcher = static function (string $table, string $kind, string $line) use ($expected): bool {
            return $expected[0]->matches($table, $kind, $line, 'mysql', 'upgrade-from-v4.6.0..v4.6.28');
        };

        $differences = SchemaComparer::compare(
            ['ibexa_page' => ['CONSTRAINT `fk_ibexa_page_source_page` FOREIGN KEY (`source_page_id`)']],
            ['ibexa_page' => ['CONSTRAINT `fk_ezpage_pages_source_page` FOREIGN KEY (`source_page_id`)']],
            $matcher
        );

        self::assertCount(1, $differences);
        self::assertSame([], $differences[0]->getUnexpectedLines());
        self::assertSame(['CONSTRAINT `fk_ibexa_page_source_page` FOREIGN KEY (`source_page_id`)'], $differences[0]->getMissingLines());
    }

    public function testExpectedDifferenceLimits(): void
    {
        $difference = ExpectedDifference::missing('t', 'KEY *')->onPlatforms('postgresql')->inScenarios('fresh-install');

        self::assertTrue($difference->matches('t', ExpectedDifference::MISSING, 'KEY `a` (`a`)', 'postgresql', 'fresh-install'));
        self::assertFalse($difference->matches('t', ExpectedDifference::UNEXPECTED, 'KEY `a` (`a`)', 'postgresql', 'fresh-install'));
        self::assertFalse($difference->matches('t', ExpectedDifference::MISSING, 'KEY `a` (`a`)', 'mysql', 'fresh-install'));
        self::assertFalse($difference->matches('t', ExpectedDifference::MISSING, 'KEY `a` (`a`)', 'postgresql', 'current-legacy-install'));
        self::assertFalse($difference->matches('other', ExpectedDifference::MISSING, 'KEY `a` (`a`)', 'postgresql', 'fresh-install'));
        self::assertFalse($difference->matches('t', ExpectedDifference::MISSING, 'UNIQUE KEY `a` (`a`)', 'postgresql', 'fresh-install'));
    }

    /**
     * @return callable(string, string, string): bool
     */
    private static function nothingExpected(): callable
    {
        return static fn (string $table, string $kind, string $line): bool => false;
    }
}
