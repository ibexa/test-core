<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Ibexa\Contracts\Core\Test\Persistence\Fixture;
use Ibexa\Contracts\Core\Test\Persistence\Fixture\FixtureImporter;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\FixtureHook
 */
final class FixtureHookTest extends TestCase
{
    public function testLoadFixturesOptionDefaultsToTrue(): void
    {
        $options = $this->resolve($this->hookReturningFixtures([]), []);

        self::assertTrue($options[FixtureHook::OPTION_LOAD_FIXTURES]);
    }

    public function testAsksProviderForFixturesWhenEnabled(): void
    {
        $fixture = $this->createMock(Fixture::class);
        $fixture->method('load')->willReturn([]);

        $provider = $this->createMock(FixtureProviderInterface::class);
        $provider->expects(self::once())
            ->method('getFixtures')
            ->willReturn([$fixture]);

        $connection = $this->createMock(Connection::class);
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('supportsSequences')->willReturn(false);
        $connection->method('getDatabasePlatform')->willReturn($platform);

        $hook = new FixtureHook($provider, new FixtureImporter($connection));
        $hook($this->resolve($hook, []));
    }

    public function testDoesNotAskProviderForFixturesWhenDisabled(): void
    {
        $provider = $this->createMock(FixtureProviderInterface::class);
        $provider->expects(self::never())->method('getFixtures');

        $hook = new FixtureHook($provider, new FixtureImporter($this->createMock(Connection::class)));
        $hook($this->resolve($hook, [FixtureHook::OPTION_LOAD_FIXTURES => false]));
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolve(FixtureHook $hook, array $options): array
    {
        $resolver = new OptionsResolver();
        $hook->configureOptions($resolver);

        return $resolver->resolve($options);
    }

    /**
     * @param list<\Ibexa\Contracts\Core\Test\Persistence\Fixture> $fixtures
     */
    private function hookReturningFixtures(array $fixtures): FixtureHook
    {
        $provider = $this->createMock(FixtureProviderInterface::class);
        $provider->method('getFixtures')->willReturn($fixtures);

        $fixtureImporter = new FixtureImporter($this->createMock(Connection::class));

        return new FixtureHook($provider, $fixtureImporter);
    }
}
