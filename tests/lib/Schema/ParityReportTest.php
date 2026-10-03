<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Schema;

use Ibexa\Contracts\Test\Core\Schema\ReleasedSchema;
use Ibexa\Test\Core\Schema\ParityReport;
use Ibexa\Test\Core\Schema\Scenario;
use Ibexa\Test\Core\Schema\TableDifference;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Test\Core\Schema\ParityReport
 * @covers \Ibexa\Test\Core\Schema\Scenario
 */
final class ParityReportTest extends TestCase
{
    public function testFormat(): void
    {
        $report = ParityReport::format(
            Scenario::upgradeFrom(new ReleasedSchema('v4.6.0..v4.6.28', "tables: {}\n")),
            'mysql',
            '8.0.40',
            'mysql://mysql:mysql@db/testdb_parity_reference',
            'mysql://mysql:mysql@db/testdb_parity_upgrade_v4_6_28',
            [
                new TableDifference(
                    'ezpage_pages',
                    ['KEY `ezpage_pages_source_page_id` (`source_page_id`)'],
                    ['KEY `fk_ezpage_pages_source_page` (`source_page_id`)']
                ),
                new TableDifference('ezpage_new', [], [], true),
            ]
        );

        self::assertSame(
            <<<'TXT'
                Scenario "upgrade-from-v4.6.0..v4.6.28" on mysql 8.0.40: the migrations path ended with a different schema than the schema path.
                Starting point: a legacy install where this package's tables are as they were at v4.6.0..v4.6.28.
                Likely cause: a schema change made since then has no migration, or its migration doesn't produce what the schema definition declares. Add or fix a migration guarded with hasTable/hasColumn/hasIndex.
                Databases kept for inspection: schema path "mysql://mysql:***@db/testdb_parity_reference", migrations path "mysql://mysql:***@db/testdb_parity_upgrade_v4_6_28".
                "-" lines exist only on the schema path, "+" lines only on the migrations path.

                ezpage_pages:
                  - KEY `ezpage_pages_source_page_id` (`source_page_id`)
                  + KEY `fk_ezpage_pages_source_page` (`source_page_id`)

                ezpage_new: the migrations path doesn't create this table.
                TXT,
            $report
        );
    }

    public function testScenarioIds(): void
    {
        self::assertSame('fresh-install', Scenario::freshInstall()->getId());
        self::assertSame('current-legacy-install', Scenario::currentLegacyInstall(['v4.6.29', 'v4.6.32'])->getId());
        self::assertStringContainsString('v4.6.29..v4.6.32', Scenario::currentLegacyInstall(['v4.6.29', 'v4.6.32'])->getStartingPoint());
        self::assertSame('upgrade_v4.6.20', Scenario::upgradeFrom(new ReleasedSchema('v4.6.0..v4.6.20', ''))->getDatabaseSlug());
        self::assertSame('upgrade_v4.6.9', Scenario::upgradeFrom(new ReleasedSchema('v4.6.0..v4.6.3+v4.6.9', ''))->getDatabaseSlug());
    }
}
