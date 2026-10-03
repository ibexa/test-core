<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema\Ddl;

use Doctrine\DBAL\Connection;
use RuntimeException;

/**
 * MySQL and MariaDB: SHOW CREATE TABLE, which is the DDL mysqldump prints.
 *
 * @internal
 */
final class MySqlDdlRenderer implements DdlRendererInterface
{
    public function render(Connection $connection, string $table): ?array
    {
        $exists = $connection->fetchOne(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?',
            [$table]
        );
        if ((int)$exists === 0) {
            return null;
        }

        $row = $connection->fetchNumeric('SHOW CREATE TABLE ' . $connection->quoteIdentifier($table));
        if ($row === false || !isset($row[1]) || !is_string($row[1])) {
            throw new RuntimeException(sprintf('SHOW CREATE TABLE returned nothing for "%s".', $table));
        }

        return self::normalize($row[1]);
    }

    public function getServerVersion(Connection $connection): string
    {
        return (string)$connection->fetchOne('SELECT VERSION()');
    }

    /**
     * Turns SHOW CREATE TABLE output into sorted lines: column order doesn't matter (a column added
     * by ALTER TABLE ends up last), and neither does the AUTO_INCREMENT counter.
     *
     * @return list<string>
     */
    public static function normalize(string $createTable): array
    {
        $lines = preg_split('/\R/', trim($createTable));
        if ($lines === false || count($lines) < 2) {
            throw new RuntimeException(sprintf('Unexpected SHOW CREATE TABLE output: %s', $createTable));
        }

        array_shift($lines);
        $tableOptions = (string)array_pop($lines);
        $tableOptions = trim((string)preg_replace('/\s+AUTO_INCREMENT=\d+/', '', ltrim($tableOptions, ') ')));

        $definitions = [];
        foreach ($lines as $line) {
            $definitions[] = rtrim(trim($line), ',');
        }
        sort($definitions, SORT_STRING);
        $definitions[] = 'table options: ' . $tableOptions;

        return $definitions;
    }
}
