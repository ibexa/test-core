<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\IbexaTestKernelInterface;
use Ibexa\Contracts\Core\Test\Persistence\Fixture;
use Ibexa\Contracts\Core\Test\Persistence\Fixture\YamlFixture;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;

/**
 * The built-in base fixture {@see IbexaTestKernel} contributes by
 * default. Deliberately not tagged as a {@see FixtureProviderInterface} — the kernel-method fallback
 * ({@see FixtureKernelMethodProvider}) already always wins for every kernel, since
 * {@see IbexaTestKernelInterface} makes `getFixtures()` mandatory. This
 * class exists purely so another provider can constructor-inject it directly to compose with the
 * built-in default without going through a Kernel method.
 */
final class DefaultFixtureProvider
{
    /**
     * @return iterable<Fixture>
     */
    public function getFixtures(): iterable
    {
        yield new YamlFixture(dirname(__DIR__) . '/Resources/test_data.yaml');
    }
}
