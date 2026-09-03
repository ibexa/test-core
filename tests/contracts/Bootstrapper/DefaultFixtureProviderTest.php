<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\Persistence\Fixture\YamlFixture;
use Ibexa\Contracts\Test\Core\Bootstrapper\DefaultFixtureProvider;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\DefaultFixtureProvider
 */
final class DefaultFixtureProviderTest extends TestCase
{
    public function testYieldsTheBuiltInBaseFixture(): void
    {
        $provider = new DefaultFixtureProvider();

        $fixtures = [];
        foreach ($provider->getFixtures() as $fixture) {
            $fixtures[] = $fixture;
        }

        self::assertCount(1, $fixtures);
        self::assertInstanceOf(YamlFixture::class, $fixtures[0]);
    }
}
