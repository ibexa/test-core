<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\Persistence\Fixture\YamlFixture;

/**
 * Defines the baseline repository content every kernel starts from.
 *
 * Deliberately not tagged as a {@see FixtureProviderInterface}: the baseline is not something the
 * provider chain chooses between, it is imported unconditionally by {@see BaseFixtureHook}. The
 * chain covers what a package contributes on top. A provider that needs the baseline's rows to
 * build its own fixtures can still constructor-inject this class directly, but it should not yield
 * them again - the hook has already imported them by the time {@see FixtureHook} runs.
 */
final class DefaultFixtureProvider
{
    /**
     * @return iterable<\Ibexa\Contracts\Core\Test\Persistence\Fixture>
     */
    public function getFixtures(): iterable
    {
        yield new YamlFixture(dirname(__DIR__) . '/Resources/test_data.yaml');
    }
}
