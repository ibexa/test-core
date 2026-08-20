<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;
use Ibexa\Test\Core\Bootstrapper\FixtureHook;
use Ibexa\Test\Core\Bootstrapper\HooksExecutor;
use Ibexa\Test\Core\Bootstrapper\PurgeIndexHook;
use Ibexa\Test\Core\Bootstrapper\SchemaHook;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();
    $services->defaults()
        ->autowire()
        ->autoconfigure()
        ->private();

    $services->alias(HooksExecutorInterface::class, HooksExecutor::class);

    $services->set(HooksExecutor::class)
        ->arg('$hooks', tagged_iterator('ibexa.test.bootstrapper.hook'));

    $services->set(SchemaHook::class)
        ->tag('ibexa.test.bootstrapper.hook', ['priority' => 1000]);

    $services->set(FixtureHook::class)
        ->tag('ibexa.test.bootstrapper.hook', ['priority' => 900]);

    $services->set(PurgeIndexHook::class)
        ->arg('$handler', service('ibexa.spi.search'))
        ->tag('ibexa.test.bootstrapper.hook', ['priority' => 100]);
};
