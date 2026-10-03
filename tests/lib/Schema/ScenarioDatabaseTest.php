<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Schema;

use Ibexa\Test\Core\Schema\ScenarioDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Test\Core\Schema\ScenarioDatabase
 */
final class ScenarioDatabaseTest extends TestCase
{
    /**
     * @dataProvider provideUrls
     */
    public function testDeriveUrl(string $baseUrl, string $slug, string $expected): void
    {
        self::assertSame($expected, ScenarioDatabase::deriveUrl($baseUrl, $slug));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function provideUrls(): iterable
    {
        yield 'mysql, query string kept' => [
            'mysql://mysql:mysql@127.0.0.1:3306/testdb?serverVersion=8.0',
            'fresh',
            'mysql://mysql:mysql@127.0.0.1:3306/testdb_parity_fresh?serverVersion=8.0',
        ];
        yield 'postgresql' => [
            'pgsql://postgres:postgres@localhost:5432/testdb?server_version=14',
            'upgrade_v4.6.28',
            'pgsql://postgres:postgres@localhost:5432/testdb_parity_upgrade_v4_6_28?server_version=14',
        ];
        yield 'sqlite, relative path' => [
            'sqlite://i@i/var/test.db',
            'reference',
            'sqlite://i@i/var/test_parity_reference.db',
        ];
        yield 'sqlite, absolute path' => [
            'sqlite:////tmp/x/test.sqlite',
            'legacy',
            'sqlite:////tmp/x/test_parity_legacy.sqlite',
        ];
    }

    public function testDeriveUrlKeepsDatabaseNamesWithinTheLimit(): void
    {
        $url = ScenarioDatabase::deriveUrl(
            'mysql://u:p@db/' . str_repeat('a', 40),
            'upgrade_v4.6.0..v4.6.20+v4.6.22..v4.6.28'
        );

        $name = (string)parse_url($url, PHP_URL_PATH);
        self::assertLessThanOrEqual(64, strlen(ltrim($name, '/')));
    }

    public function testDeriveUrlRejectsInMemorySqlite(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScenarioDatabase::deriveUrl('sqlite:///:memory:', 'fresh');
    }

    public function testGetSqliteFile(): void
    {
        self::assertSame('var/test_parity_fresh.db', ScenarioDatabase::getSqliteFile('sqlite://i@i/var/test_parity_fresh.db'));
        self::assertSame('/tmp/x/test.db', ScenarioDatabase::getSqliteFile('sqlite:////tmp/x/test.db'));
        self::assertNull(ScenarioDatabase::getSqliteFile('mysql://u:p@db/testdb'));
    }

    public function testMaskHidesThePassword(): void
    {
        self::assertSame('mysql://mysql:***@127.0.0.1/testdb', ScenarioDatabase::mask('mysql://mysql:secret@127.0.0.1/testdb'));
        self::assertSame('sqlite://i@i/var/test.db', ScenarioDatabase::mask('sqlite://i@i/var/test.db'));
    }
}
