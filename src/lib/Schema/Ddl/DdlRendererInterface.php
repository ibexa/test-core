<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema\Ddl;

use Doctrine\DBAL\Connection;

/**
 * Reads a table's definition the way the database itself renders it (the same DDL mysqldump or
 * pg_dump would print), normalized so that equal tables give equal lines: one line per column,
 * key and constraint, sorted, without counters such as AUTO_INCREMENT.
 *
 * Only plain queries through the connection are used, so it works with any DBAL version and needs
 * no database client binaries.
 *
 * @internal
 */
interface DdlRendererInterface
{
    /**
     * @return list<string>|null null when the table doesn't exist
     */
    public function render(Connection $connection, string $table): ?array;

    public function getServerVersion(Connection $connection): string;
}
