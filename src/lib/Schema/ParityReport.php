<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema;

/**
 * Formats a schema parity failure so that it says where the scenario started, what differs, and
 * what the usual cause is.
 *
 * @internal
 */
final class ParityReport
{
    /**
     * @param list<TableDifference> $differences
     */
    public static function format(
        Scenario $scenario,
        string $platform,
        string $serverVersion,
        string $referenceUrl,
        string $scenarioUrl,
        array $differences
    ): string {
        $lines = [
            sprintf(
                'Scenario "%s" on %s %s: the migrations path ended with a different schema than the schema path.',
                $scenario->getId(),
                $platform,
                $serverVersion
            ),
            sprintf('Starting point: %s.', $scenario->getStartingPoint()),
            sprintf('Likely cause: %s', $scenario->getLikelyCause()),
            sprintf(
                'Databases kept for inspection: schema path "%s", migrations path "%s".',
                ScenarioDatabase::mask($referenceUrl),
                ScenarioDatabase::mask($scenarioUrl)
            ),
            '"-" lines exist only on the schema path, "+" lines only on the migrations path.',
        ];

        foreach ($differences as $difference) {
            $lines[] = '';
            if ($difference->isTableMissing()) {
                $lines[] = sprintf('%s: the migrations path doesn\'t create this table.', $difference->getTable());
                continue;
            }

            if ($difference->isTableUnexpected()) {
                $lines[] = sprintf(
                    '%s: the migrations path leaves this table behind, but the schema path doesn\'t have it.',
                    $difference->getTable()
                );
                continue;
            }

            $lines[] = $difference->getTable() . ':';
            foreach ($difference->getMissingLines() as $line) {
                $lines[] = '  - ' . $line;
            }
            foreach ($difference->getUnexpectedLines() as $line) {
                $lines[] = '  + ' . $line;
            }
        }

        return implode("\n", $lines);
    }
}
