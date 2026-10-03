<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Schema\Ddl;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Ibexa\Test\Core\Schema\Ddl\DdlRenderers;
use Ibexa\Test\Core\Schema\Ddl\SqliteDdlRenderer;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Test\Core\Schema\Ddl\DdlRenderers
 * @covers \Ibexa\Test\Core\Schema\Ddl\SqliteDdlRenderer
 */
final class SqliteDdlRendererTest extends TestCase
{
    public function testRendersColumnsIndexesAndForeignKeys(): void
    {
        $connection = self::createTables([
            'CREATE TABLE parent (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL)',
            "CREATE TABLE child (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, parent_id INTEGER DEFAULT NULL, name VARCHAR(255) DEFAULT '' NOT NULL, CONSTRAINT fk_child_parent FOREIGN KEY (parent_id) REFERENCES parent (id) ON DELETE CASCADE)",
            'CREATE INDEX child_parent_id ON child (parent_id)',
        ]);

        self::assertSame('sqlite', DdlRenderers::getPlatformName($connection));
        self::assertSame(
            [
                'column id INTEGER notnull=1 default=NULL pk=1',
                "column name VARCHAR(255) notnull=1 default='' pk=0",
                'column parent_id INTEGER notnull=0 default=NULL pk=0',
                'foreign-key (parent_id) references parent(id) on-update=NO ACTION on-delete=CASCADE',
                'index child_parent_id unique=0 origin=c partial=0 (parent_id)',
            ],
            (new SqliteDdlRenderer())->render($connection, 'child')
        );
    }

    public function testFormattingAndColumnOrderDontMatter(): void
    {
        $created = self::createTables([
            'CREATE TABLE p (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, source_id INTEGER DEFAULT NULL, CONSTRAINT fk FOREIGN KEY (source_id) REFERENCES p (id))',
        ]);
        $altered = self::createTables([
            "CREATE TABLE p (\n    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL\n)",
            'ALTER TABLE p ADD COLUMN source_id INTEGER DEFAULT NULL CONSTRAINT fk REFERENCES p (id)',
        ]);

        $renderer = new SqliteDdlRenderer();
        self::assertSame($renderer->render($created, 'p'), $renderer->render($altered, 'p'));
    }

    public function testMissingTableRendersAsNull(): void
    {
        self::assertNull((new SqliteDdlRenderer())->render(self::createTables([]), 'nope'));
    }

    /**
     * @param list<string> $statements
     */
    private static function createTables(array $statements): Connection
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        foreach ($statements as $statement) {
            $connection->executeStatement($statement);
        }

        return $connection;
    }
}
