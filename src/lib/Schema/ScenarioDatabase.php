<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema;

use InvalidArgumentException;

/**
 * Derives a separate database per schema parity scenario from the suite's DATABASE_URL, so every
 * scenario starts from its own database and the databases can be inspected side by side afterwards.
 *
 * "mysql://u:p@host/testdb?serverVersion=8.0" becomes "mysql://u:p@host/testdb_parity_fresh?serverVersion=8.0",
 * and "sqlite://i@i/var/test.db" becomes "sqlite://i@i/var/test_parity_fresh.db".
 *
 * @internal
 */
final class ScenarioDatabase
{
    /** MySQL allows 64 characters in a database name, PostgreSQL 63. */
    private const MAX_NAME_LENGTH = 63;

    public static function deriveUrl(string $baseUrl, string $slug): string
    {
        if (preg_match('~^(?<prefix>[a-z][a-z0-9+.-]*://[^/?#]*)(?<path>/[^?#]*)(?<rest>.*)$~i', $baseUrl, $matches) !== 1) {
            throw new InvalidArgumentException(sprintf('Unable to derive a schema parity database from "%s".', self::mask($baseUrl)));
        }

        $slug = strtolower((string)preg_replace('/[^a-z0-9]+/i', '_', $slug));
        $slug = trim($slug, '_');
        $path = $matches['path'];

        if (stripos($matches['prefix'], 'sqlite') === 0) {
            if ($path === '/:memory:') {
                throw new InvalidArgumentException('Schema parity scenarios need a file-based SQLite database, not ":memory:".');
            }

            $directory = dirname($path);
            $file = basename($path);
            $extensionPosition = strrpos($file, '.');
            $name = $extensionPosition === false ? $file : substr($file, 0, $extensionPosition);
            $extension = $extensionPosition === false ? '' : substr($file, $extensionPosition);

            return sprintf('%s%s/%s_parity_%s%s%s', $matches['prefix'], rtrim($directory, '/'), $name, $slug, $extension, $matches['rest']);
        }

        $name = ltrim($path, '/');
        if ($name === '' || strpos($name, '/') !== false) {
            throw new InvalidArgumentException(sprintf('Unable to find the database name in "%s".', self::mask($baseUrl)));
        }

        $scenarioName = $name . '_parity_' . $slug;
        if (strlen($scenarioName) > self::MAX_NAME_LENGTH) {
            $scenarioName = substr($scenarioName, 0, self::MAX_NAME_LENGTH - 9) . '_' . substr(md5($slug), 0, 8);
        }

        return sprintf('%s/%s%s', $matches['prefix'], $scenarioName, $matches['rest']);
    }

    /**
     * Returns the SQLite database file of a URL, or null for other databases.
     */
    public static function getSqliteFile(string $url): ?string
    {
        if (preg_match('~^sqlite[a-z0-9+.-]*://[^/?#]*/(?<path>[^?#]*)~i', $url, $matches) !== 1) {
            return null;
        }

        return $matches['path'];
    }

    /**
     * Hides the password, for messages.
     */
    public static function mask(string $url): string
    {
        return (string)preg_replace('~^([a-z][a-z0-9+.-]*://[^:/@]+):[^@/]*@~i', '$1:***@', $url);
    }
}
