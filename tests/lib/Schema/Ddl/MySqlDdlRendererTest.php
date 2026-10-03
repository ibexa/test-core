<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Schema\Ddl;

use Ibexa\Test\Core\Schema\Ddl\MySqlDdlRenderer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Test\Core\Schema\Ddl\MySqlDdlRenderer
 */
final class MySqlDdlRendererTest extends TestCase
{
    public function testNormalizeIgnoresColumnOrderAndCounters(): void
    {
        $fresh = <<<'SQL'
            CREATE TABLE `ezpage_pages` (
              `id` int NOT NULL AUTO_INCREMENT,
              `source_page_id` int DEFAULT NULL,
              `version_no` int NOT NULL,
              PRIMARY KEY (`id`),
              KEY `ezpage_pages_source_page_id` (`source_page_id`),
              CONSTRAINT `fk_ezpage_pages_source_page` FOREIGN KEY (`source_page_id`) REFERENCES `ezpage_pages` (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
            SQL;

        $upgraded = <<<'SQL'
            CREATE TABLE `ezpage_pages` (
              `id` int NOT NULL AUTO_INCREMENT,
              `version_no` int NOT NULL,
              `source_page_id` int DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `ezpage_pages_source_page_id` (`source_page_id`),
              CONSTRAINT `fk_ezpage_pages_source_page` FOREIGN KEY (`source_page_id`) REFERENCES `ezpage_pages` (`id`)
            ) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci
            SQL;

        self::assertSame(MySqlDdlRenderer::normalize($fresh), MySqlDdlRenderer::normalize($upgraded));
        self::assertSame(
            [
                'CONSTRAINT `fk_ezpage_pages_source_page` FOREIGN KEY (`source_page_id`) REFERENCES `ezpage_pages` (`id`)',
                'KEY `ezpage_pages_source_page_id` (`source_page_id`)',
                'PRIMARY KEY (`id`)',
                '`id` int NOT NULL AUTO_INCREMENT',
                '`source_page_id` int DEFAULT NULL',
                '`version_no` int NOT NULL',
                'table options: ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci',
            ],
            MySqlDdlRenderer::normalize($fresh)
        );
    }

    public function testNormalizeKeepsWhatMatters(): void
    {
        $withIndex = "CREATE TABLE `t` (\n  `a` int DEFAULT NULL,\n  KEY `t_a` (`a`)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $withoutIndex = "CREATE TABLE `t` (\n  `a` int DEFAULT NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $otherCharset = "CREATE TABLE `t` (\n  `a` int DEFAULT NULL,\n  KEY `t_a` (`a`)\n) ENGINE=InnoDB DEFAULT CHARSET=latin1";

        self::assertNotSame(MySqlDdlRenderer::normalize($withIndex), MySqlDdlRenderer::normalize($withoutIndex));
        self::assertNotSame(MySqlDdlRenderer::normalize($withIndex), MySqlDdlRenderer::normalize($otherCharset));
    }
}
