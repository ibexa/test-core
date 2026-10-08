<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Search\VersatileHandler;
use Ibexa\Contracts\Test\Core\Bootstrapper\PurgeIndexAfterFixturesHook;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\PurgeIndexAfterFixturesHook
 */
final class PurgeIndexAfterFixturesHookTest extends TestCase
{
    public function testPurgeIndexOptionDefaultsToFalse(): void
    {
        $handler = $this->createMock(VersatileHandler::class);
        $handler->expects(self::never())->method('purgeIndex');
        $hook = new PurgeIndexAfterFixturesHook($handler);

        $options = $this->resolve($hook, []);

        self::assertFalse($options[PurgeIndexAfterFixturesHook::OPTION_PURGE_INDEX]);

        $hook($options);
    }

    public function testPurgesIndexWhenEnabled(): void
    {
        $handler = $this->createMock(VersatileHandler::class);
        $handler->expects(self::once())->method('purgeIndex');
        $hook = new PurgeIndexAfterFixturesHook($handler);

        $options = $this->resolve($hook, [PurgeIndexAfterFixturesHook::OPTION_PURGE_INDEX => true]);
        $hook($options);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function resolve(
        PurgeIndexAfterFixturesHook $hook,
        array $options
    ): array {
        $resolver = new OptionsResolver();
        $hook->configureOptions($resolver);

        return $resolver->resolve($options);
    }
}
