<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema\Ddl;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use RuntimeException;

/**
 * Picks the renderer for a connection's platform. Platform classes are matched by name, so this
 * works across DBAL 2.13 (MySqlPlatform, PostgreSqlPlatform, SqlitePlatform), DBAL 3 and DBAL 4
 * (AbstractMySQLPlatform, PostgreSQLPlatform, SQLitePlatform): PHP class names are case-insensitive.
 *
 * @internal
 */
final class DdlRenderers
{
    private const MARIADB_PLATFORMS = [
        'Doctrine\DBAL\Platforms\MariaDBPlatform',
        'Doctrine\DBAL\Platforms\MariaDb1027Platform',
    ];

    private const MYSQL_PLATFORMS = [
        'Doctrine\DBAL\Platforms\AbstractMySQLPlatform',
        'Doctrine\DBAL\Platforms\MySqlPlatform',
    ];

    private const POSTGRESQL_PLATFORMS = [
        'Doctrine\DBAL\Platforms\PostgreSQLPlatform',
    ];

    private const SQLITE_PLATFORMS = [
        'Doctrine\DBAL\Platforms\SqlitePlatform',
    ];

    public static function forConnection(Connection $connection): DdlRendererInterface
    {
        switch (self::getPlatformName($connection)) {
            case 'mysql':
            case 'mariadb':
                return new MySqlDdlRenderer();
            case 'postgresql':
                return new PostgreSqlDdlRenderer();
            default:
                return new SqliteDdlRenderer();
        }
    }

    /**
     * @return 'mysql'|'mariadb'|'postgresql'|'sqlite'
     */
    public static function getPlatformName(Connection $connection): string
    {
        $platform = $connection->getDatabasePlatform();

        if (self::isAny($platform, self::MARIADB_PLATFORMS)) {
            return 'mariadb';
        }
        if (self::isAny($platform, self::MYSQL_PLATFORMS)) {
            return 'mysql';
        }
        if (self::isAny($platform, self::POSTGRESQL_PLATFORMS)) {
            return 'postgresql';
        }
        if (self::isAny($platform, self::SQLITE_PLATFORMS)) {
            return 'sqlite';
        }

        throw new RuntimeException(sprintf('Schema parity checks don\'t support the %s platform.', get_class($platform)));
    }

    /**
     * is_a() compares against the platform's already loaded class hierarchy without autoloading,
     * so a candidate that doesn't exist in the installed DBAL version is simply never matched.
     *
     * @param list<string> $classes
     */
    private static function isAny(AbstractPlatform $platform, array $classes): bool
    {
        foreach ($classes as $class) {
            if (is_a($platform, $class)) {
                return true;
            }
        }

        return false;
    }
}
