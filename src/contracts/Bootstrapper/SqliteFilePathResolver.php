<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use LogicException;

/**
 * @internal
 */
final class SqliteFilePathResolver
{
    /**
     * Extracts the filesystem path out of a sqlite DATABASE_URL, matching Doctrine's own
     * "sqlite://i@i/path/to/file.db" convention: the URL's own leading slash (the one separating
     * the fake "i@i" host from the path) is stripped, leaving the path as configured — relative to
     * the current working directory for a relative path, or, if the configured path is itself
     * absolute (e.g. "sqlite://i@i/%kernel.project_dir%/var/data.db"), still absolute, since only
     * one slash is stripped rather than every leading slash. Returns null for "sqlite://:memory:",
     * which has no file to clean up.
     *
     * @throws \LogicException if $databaseUrl uses one of Doctrine's other valid sqlite DSN forms
     *                         ("sqlite:///path" or "sqlite:////abs/path") — Doctrine's own
     *                         DriverManager special-cases and rewrites these before connecting, but
     *                         PHP's parse_url() can't parse them at all (returns false, not null),
     *                         so the file to clean up can't be determined; silently skipping
     *                         cleanup here would leave stale data for the next bootstrap run.
     */
    public function resolve(string $databaseUrl): ?string
    {
        $path = parse_url($databaseUrl, PHP_URL_PATH);

        if ($path === null) {
            return null;
        }

        if ($path === false) {
            throw new LogicException(sprintf(
                'Could not determine a file path from sqlite DATABASE_URL "%s": PHP\'s parse_url()'
                . ' can\'t parse the "sqlite:///path" or "sqlite:////abs/path" DSN forms, even though'
                . ' Doctrine itself accepts them. Use the "sqlite://i@i/path/to/file.db" convention instead.',
                $databaseUrl,
            ));
        }

        return str_starts_with($path, '/') ? substr($path, 1) : $path;
    }
}
