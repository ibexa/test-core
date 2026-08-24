<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\Persistence\Fixture;

/**
 * @experimental
 *
 * Supplies {@see FixtureHook} with the list of fixtures to import.
 *
 * Register an implementation as a service tagged {@see self::TAG} (with an optional "priority" tag
 * attribute) to have it picked up automatically alongside the built-in providers.
 */
interface FixtureProviderInterface
{
    public const TAG = 'ibexa.test.bootstrapper.fixture_provider';

    /**
     * @return iterable<Fixture>|null null if this provider has nothing to contribute — the caller
     *                                 should fall back to another provider, not treat this the same
     *                                 as a deliberately empty list
     */
    public function getFixtures(): ?iterable;
}
