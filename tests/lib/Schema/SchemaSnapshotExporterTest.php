<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Schema;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Ibexa\DoctrineSchema\Importer\SchemaImporter;
use Ibexa\Test\Core\Schema\SchemaSnapshotExporter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Test\Core\Schema\SchemaSnapshotExporter
 */
final class SchemaSnapshotExporterTest extends TestCase
{
    public function testExportedTablesImportBackAsTheyWere(): void
    {
        $schema = new Schema();
        $parent = $schema->createTable('parent_table');
        $parent->addColumn('id', 'integer', ['autoincrement' => true]);
        $parent->setPrimaryKey(['id']);

        $child = $schema->createTable('child_table');
        $child->addColumn('id', 'integer', ['autoincrement' => true]);
        $child->addColumn('parent_id', 'integer', ['notnull' => false]);
        $child->addColumn('price', 'decimal', ['precision' => 19, 'scale' => 4]);
        $child->addColumn('code', 'string', ['length' => 36, 'fixed' => true, 'comment' => 'a code']);
        $child->addColumn('quantity', 'integer', ['unsigned' => true]);
        $child->setPrimaryKey(['id']);
        $child->addIndex(['parent_id'], 'IDX_PARENT');
        $child->addForeignKeyConstraint('parent_table', ['parent_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_CHILD_PARENT');

        $yaml = (new SchemaSnapshotExporter())->export($schema, ['parent_table', 'child_table', 'not_there']);
        $imported = (new SchemaImporter())->importFromSource($yaml);

        self::assertSame(
            ['parent_table', 'child_table'],
            array_values(array_map(static fn (Table $table): string => $table->getName(), $imported->getTables()))
        );
        $platform = new SqlitePlatform();
        self::assertSame($schema->toSql($platform), $imported->toSql($platform));
        self::assertSame('FK_CHILD_PARENT', array_values($imported->getTable('child_table')->getForeignKeys())[0]->getName());
        self::assertSame('a code', $imported->getTable('child_table')->getColumn('code')->getComment());
        self::assertTrue($imported->getTable('child_table')->getColumn('quantity')->getUnsigned());
    }
}
