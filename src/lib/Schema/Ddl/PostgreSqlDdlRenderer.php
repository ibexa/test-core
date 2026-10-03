<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema\Ddl;

use Doctrine\DBAL\Connection;

/**
 * PostgreSQL: the catalog functions pg_dump itself renders DDL with (format_type, pg_get_expr,
 * pg_get_constraintdef, pg_get_indexdef), one line per column, constraint and index.
 *
 * @internal
 */
final class PostgreSqlDdlRenderer implements DdlRendererInterface
{
    private const TABLE_OID = <<<'SQL'
        SELECT c.oid
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE c.relname = ? AND n.nspname = current_schema() AND c.relkind IN ('r', 'p')
        SQL;

    private const COLUMNS = <<<'SQL'
        SELECT
            a.attname AS name,
            format_type(a.atttypid, a.atttypmod) AS type,
            a.attnotnull AS not_null,
            pg_get_expr(d.adbin, d.adrelid) AS default_value,
            a.attidentity AS identity,
            CASE WHEN a.attcollation <> 0 AND a.attcollation <> t.typcollation THEN co.collname END AS collation,
            col_description(a.attrelid, a.attnum) AS comment
        FROM pg_attribute a
        JOIN pg_type t ON t.oid = a.atttypid
        LEFT JOIN pg_attrdef d ON d.adrelid = a.attrelid AND d.adnum = a.attnum
        LEFT JOIN pg_collation co ON co.oid = a.attcollation
        WHERE a.attrelid = CAST(? AS oid) AND a.attnum > 0 AND NOT a.attisdropped
        SQL;

    private const CONSTRAINTS = <<<'SQL'
        SELECT conname AS name, pg_get_constraintdef(oid) AS definition
        FROM pg_constraint
        WHERE conrelid = CAST(? AS oid)
        SQL;

    private const INDEXES = <<<'SQL'
        SELECT ic.relname AS name, pg_get_indexdef(i.indexrelid) AS definition
        FROM pg_index i
        JOIN pg_class ic ON ic.oid = i.indexrelid
        WHERE i.indrelid = CAST(? AS oid)
            AND NOT EXISTS (SELECT 1 FROM pg_constraint c WHERE c.conindid = i.indexrelid AND c.conrelid = i.indrelid)
        SQL;

    public function render(Connection $connection, string $table): ?array
    {
        $oid = $connection->fetchOne(self::TABLE_OID, [$table]);
        if ($oid === false || $oid === null) {
            return null;
        }
        $oid = (string)$oid;

        $lines = [];
        foreach ($connection->fetchAllAssociative(self::COLUMNS, [$oid]) as $column) {
            $line = sprintf('column %s %s', self::string($column['name']), self::string($column['type']));
            if (in_array($column['not_null'], [true, 't', 1, '1'], true)) {
                $line .= ' NOT NULL';
            }
            if ($column['default_value'] !== null) {
                $line .= ' DEFAULT ' . self::string($column['default_value']);
            }
            if ($column['identity'] !== null && $column['identity'] !== '') {
                $line .= ' IDENTITY ' . self::string($column['identity']);
            }
            if ($column['collation'] !== null) {
                $line .= ' COLLATE ' . self::string($column['collation']);
            }
            if ($column['comment'] !== null) {
                $line .= ' COMMENT ' . $connection->quote(self::string($column['comment']));
            }
            $lines[] = $line;
        }

        foreach ($connection->fetchAllAssociative(self::CONSTRAINTS, [$oid]) as $constraint) {
            $lines[] = sprintf('constraint %s %s', self::string($constraint['name']), self::string($constraint['definition']));
        }

        foreach ($connection->fetchAllAssociative(self::INDEXES, [$oid]) as $index) {
            $lines[] = sprintf('index %s', self::string($index['definition']));
        }

        sort($lines, SORT_STRING);

        return $lines;
    }

    public function getServerVersion(Connection $connection): string
    {
        return (string)$connection->fetchOne('SHOW server_version');
    }

    /**
     * @param mixed $value
     */
    private static function string($value): string
    {
        return is_scalar($value) ? (string)$value : '';
    }
}
