<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Test\Core\DependencyInjection;

use Symfony\Component\Config\FileLocator;
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
        $locator = new FileLocator(__DIR__ . '/../Resources/config');

        // A resolver containing both loaders so services.php's own import() call can resolve a
        // "services/**.yaml" glob — PhpFileLoader alone has no notion of how to load a .yaml file.
        $phpLoader = new PhpFileLoader($container, $locator);
        $phpLoader->setResolver(new LoaderResolver([
            $phpLoader,
            new YamlFileLoader($container, $locator),
        ]));

        $phpLoader->load('services.php');
    }
}
