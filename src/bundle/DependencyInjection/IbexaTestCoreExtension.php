<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Test\Core\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\DelegatingLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class IbexaTestCoreExtension extends Extension
{
    /**
     * @param array<string, mixed> $configs
     *
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        // This bundle only makes sense for a test kernel — Bootstrapper always boots one with the
        // "test" environment (see KernelProvider) — and some of its services (e.g. DatabaseSchemaHook)
        // depend on classes a consuming package only autoloads via its own autoload-dev, unavailable
        // once the bundle is present in a real app's dev/prod container (e.g. a downstream project
        // that registers IbexaTestCoreBundle without scoping it to the "test" environment).
        if ('test' !== $container->getParameter('kernel.environment')) {
            return;
        }

        $locator = new FileLocator(__DIR__ . '/../Resources/config');

        // Mirrors Kernel::getContainerLoader(), scoped to the file types services.php's own
        // "services/**.yaml" drop-in import() call actually needs to resolve.
        $resolver = new LoaderResolver([
            new PhpFileLoader($container, $locator),
            new YamlFileLoader($container, $locator),
        ]);

        (new DelegatingLoader($resolver))->load('services.php');
    }
}
