<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema;

use Ibexa\Contracts\Test\Core\Schema\ExpectedDifference;

/**
 * Compares the normalized DDL lines of the same tables in two databases.
 *
 * @internal
 */
final class SchemaComparer
{
    /**
     * @param array<string, list<string>|null> $expected table => lines, null if it doesn't exist
     * @param array<string, list<string>|null> $actual table => lines, null if it doesn't exist
     * @param callable(string $table, string $kind, string $line): bool $isExpectedDifference
     *
     * @return list<TableDifference>
     */
    public static function compare(array $expected, array $actual, callable $isExpectedDifference): array
    {
        $differences = [];
        foreach (array_keys($expected + $actual) as $table) {
            $table = (string)$table;
            $expectedLines = $expected[$table] ?? null;
            $actualLines = $actual[$table] ?? null;

            if ($expectedLines === null && $actualLines === null) {
                continue;
            }

            if ($actualLines === null) {
                $differences[] = new TableDifference($table, [], [], true);
                continue;
            }

            if ($expectedLines === null) {
                $differences[] = new TableDifference($table, [], [], false, true);
                continue;
            }

            $missing = array_values(array_filter(
                self::subtract($expectedLines, $actualLines),
                static fn (string $line): bool => !$isExpectedDifference($table, ExpectedDifference::MISSING, $line)
            ));
            $unexpected = array_values(array_filter(
                self::subtract($actualLines, $expectedLines),
                static fn (string $line): bool => !$isExpectedDifference($table, ExpectedDifference::UNEXPECTED, $line)
            ));

            $difference = new TableDifference($table, $missing, $unexpected);
            if (!$difference->isEmpty()) {
                $differences[] = $difference;
            }
        }

        return $differences;
    }

    /**
     * Lines of $from that $remove doesn't have, counting duplicates.
     *
     * @param list<string> $from
     * @param list<string> $remove
     *
     * @return list<string>
     */
    private static function subtract(array $from, array $remove): array
    {
        $left = [];
        foreach ($remove as $line) {
            $left[$line] = ($left[$line] ?? 0) + 1;
        }

        $result = [];
        foreach ($from as $line) {
            if (($left[$line] ?? 0) > 0) {
                --$left[$line];
                continue;
            }
            $result[] = $line;
        }

        return $result;
    }
}
