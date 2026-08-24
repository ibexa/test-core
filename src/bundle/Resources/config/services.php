<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\PurgeIndexHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\SchemaHook;
use Ibexa\Test\Core\Bootstrapper\HooksExecutor;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->alias(HooksExecutorInterface::class, HooksExecutor::class)
        ->public();

    $services->set(HooksExecutor::class)
        ->public()
        // Indexed by service id (the "id" attribute isn't set by any hook's tag, so every hook
        // falls back to its own service id as the key) while staying a plain priority-sorted
        // iterator - tagged_locator() would also index by service id, but its ServiceLocator
        // wrapper re-sorts entries alphabetically by key, destroying the priority order hooks rely on.
        ->arg('$hooks', tagged_iterator('ibexa.test.bootstrapper.hook', 'id'));

    $services->set(SchemaHook::class)
        ->arg('$kernel', service('kernel'))
        ->tag('ibexa.test.bootstrapper.hook', ['priority' => 1000]);

    $services->set(FixtureHook::class)
        ->arg('$kernel', service('kernel'))
        ->tag('ibexa.test.bootstrapper.hook', ['priority' => 900]);

    $services->set(PurgeIndexHook::class)
        ->arg('$handler', service('ibexa.spi.search'))
        ->tag('ibexa.test.bootstrapper.hook', ['priority' => 100]);
};
