<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

/**
 * Purges the search index between fixture import and migration execution.
 *
 * Disabled by default; pass `[self::OPTION_PURGE_INDEX => true]` as this hook's own options (keyed
 * by its own service id in the bootstrap options array) to enable it. Registered at a fixed priority
 * between {@see FixtureHook}'s (900) and ibexa/migrations' MigrationHook's (500), unlike
 * {@see PurgeSearchIndexHook}'s own fixed priority, which runs after everything else, including
 * migrations.
 */
final class PurgeIndexAfterFixturesHook extends AbstractPurgeIndexHook
{
    /**
     * Fixed tag priority this hook is registered at — between {@see FixtureHook} (900) and ibexa/
     * migrations' MigrationHook (500), if present.
     */
    public const PRIORITY = 800;
}
