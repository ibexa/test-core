<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Test\Core\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class IbexaTestCoreExtension extends Extension
{
    /**
     * @param array<string, mixed> $configs
     *
     * @throws \Exception
     */
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        // This bundle only makes sense for a test kernel — Bootstrapper always boots one with the
        // "test" environment (see KernelProvider) — and some of its services (e.g. DatabaseSchemaHook)
        // depend on classes a consuming package only autoloads via its own autoload-dev, unavailable
        // once the bundle is present in a real app's dev/prod container (e.g. a downstream project
        // that registers IbexaTestCoreBundle without scoping it to the "test" environment).
        if ('test' !== $container->getParameter('kernel.environment')) {
            return;
        }

        $loader = new PhpFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../Resources/config')
        );
        $loader->load('services.php');
    }
}
