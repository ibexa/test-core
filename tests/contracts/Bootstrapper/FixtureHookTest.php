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
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\FixtureHook
 */
final class FixtureHookTest extends TestCase
{
    public function testPostLoadFixturesOptionDefaultsToNull(): void
    {
        $hook = $this->hookReturningFixtures([]);

        $options = $this->resolve($hook, []);

        self::assertNull($options[FixtureHook::OPTION_POST_LOAD_FIXTURES]);

        // Resolving to null must not make __invoke() try to call it as a callback.
        $hook($options);
    }

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

    public function testPostLoadFixturesOptionIsDeprecated(): void
    {
        $hook = $this->hookReturningFixtures([]);
        $resolver = new OptionsResolver();
        $hook->configureOptions($resolver);

        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, E_USER_DEPRECATED);

        try {
            $resolver->resolve([FixtureHook::OPTION_POST_LOAD_FIXTURES => static function (): void {
            }]);
        } finally {
            restore_error_handler();
        }

        self::assertNotEmpty($deprecations);
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

        return @$resolver->resolve($options);
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
