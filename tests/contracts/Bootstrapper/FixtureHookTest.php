<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Doctrine\DBAL\Connection;
use Ibexa\Contracts\Core\Test\Persistence\Fixture\FixtureImporter;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\FixtureHook
 */
final class FixtureHookTest extends TestCase
{
    use ExpectDeprecationTrait;

    public function testPostLoadFixturesOptionDefaultsToNull(): void
    {
        $hook = $this->hookReturningFixtures([]);

        $options = $this->resolve($hook, []);

        self::assertNull($options[FixtureHook::OPTION_POST_LOAD_FIXTURES]);

        // Resolving to null must not make __invoke() try to call it as a callback.
        $hook($options);
    }

    /**
     * @group legacy
     */
    public function testRunsPostLoadFixturesCallbackAfterImporting(): void
    {
        $called = false;
        $hook = $this->hookReturningFixtures([]);

        $options = $this->resolve($hook, [
            FixtureHook::OPTION_POST_LOAD_FIXTURES => static function () use (&$called): void {
                $called = true;
            },
        ]);
        $hook($options);

        self::assertTrue($called);
    }

    /**
     * @group legacy
     */
    public function testDoesNotRunPostLoadFixturesCallbackWhenFixtureLoadingIsDisabled(): void
    {
        $called = false;
        $hook = $this->hookReturningFixtures([]);

        $options = $this->resolve($hook, [
            FixtureHook::OPTION_LOAD_FIXTURES => false,
            FixtureHook::OPTION_POST_LOAD_FIXTURES => static function () use (&$called): void {
                $called = true;
            },
        ]);
        $hook($options);

        self::assertFalse($called);
    }

    /**
     * @group legacy
     */
    public function testPostLoadFixturesOptionIsDeprecated(): void
    {
        $this->expectDeprecation('Since ibexa/test-core 4.6.0: The "post_load_fixtures" option is deprecated, register a separate hook (tagged "ibexa.test.bootstrapper.hook" with a lower priority than FixtureHook\'s) instead.');

        $this->resolve($this->hookReturningFixtures([]), [
            FixtureHook::OPTION_POST_LOAD_FIXTURES => static function (): void {
            },
        ]);
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
