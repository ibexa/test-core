<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema\Ddl;

use Doctrine\DBAL\Connection;

/**
 * SQLite: the table_info, index_list, index_info and foreign_key_list pragmas. The CREATE TABLE text
 * SQLite keeps in sqlite_master is the statement exactly as it was written, so it can't be compared
 * between two databases built from differently formatted SQL.
 *
 * @internal
 */
final class SqliteDdlRenderer implements DdlRendererInterface
{
    public function render(Connection $connection, string $table): ?array
    {
        $exists = $connection->fetchOne("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
        if ((int)$exists === 0) {
            return null;
        }

        $quotedTable = $connection->quoteIdentifier($table);
        $lines = [];

        foreach ($connection->fetchAllAssociative(sprintf('PRAGMA table_info(%s)', $quotedTable)) as $column) {
            $default = $column['dflt_value'];
            $lines[] = sprintf(
                'column %s %s notnull=%d default=%s pk=%d',
                self::string($column['name']),
                strtoupper(self::string($column['type'])),
                (int)$column['notnull'],
                $default === null || strtoupper(self::string($default)) === 'NULL' ? 'NULL' : self::string($default),
                (int)$column['pk']
            );
        }

        foreach ($connection->fetchAllAssociative(sprintf('PRAGMA index_list(%s)', $quotedTable)) as $index) {
            $name = self::string($index['name']);
            $columns = array_map(
                static fn (array $row): string => self::string($row['name']),
                $connection->fetchAllAssociative(sprintf('PRAGMA index_info(%s)', $connection->quoteIdentifier($name)))
            );
            $lines[] = sprintf(
                'index %s unique=%d origin=%s partial=%d (%s)',
                $name,
                (int)$index['unique'],
                self::string($index['origin'] ?? ''),
                (int)($index['partial'] ?? 0),
                implode(', ', $columns)
            );
        }

        $foreignKeys = [];
        foreach ($connection->fetchAllAssociative(sprintf('PRAGMA foreign_key_list(%s)', $quotedTable)) as $reference) {
            $id = (int)$reference['id'];
            $foreignKeys[$id]['table'] = self::string($reference['table']);
            $foreignKeys[$id]['from'][(int)$reference['seq']] = self::string($reference['from']);
            $foreignKeys[$id]['to'][(int)$reference['seq']] = self::string($reference['to']);
            $foreignKeys[$id]['actions'] = sprintf(
                'on-update=%s on-delete=%s',
                self::string($reference['on_update']),
                self::string($reference['on_delete'])
            );
        }
        foreach ($foreignKeys as $foreignKey) {
            ksort($foreignKey['from']);
            ksort($foreignKey['to']);
            $lines[] = sprintf(
                'foreign-key (%s) references %s(%s) %s',
                implode(', ', $foreignKey['from']),
                $foreignKey['table'],
                implode(', ', $foreignKey['to']),
                $foreignKey['actions']
            );
        }

        sort($lines, SORT_STRING);

        return $lines;
    }

    public function getServerVersion(Connection $connection): string
    {
        return (string)$connection->fetchOne('SELECT sqlite_version()');
    }

    /**
     * @param mixed $value
     */
    private static function string($value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }
}
