<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Test\Core;

use Ibexa\Bundle\Test\Core\DependencyInjection\CompilerPass\PersistenceCheckCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class IbexaTestCoreBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PersistenceCheckCompilerPass(), PassConfig::TYPE_AFTER_REMOVING);

        // DatabaseSchemaHook autowires SchemaBuilderInterface, provided by DoctrineSchemaBundle —
        // only registered here (not IbexaTestCoreExtension::load()) since all bundles' extensions
        // are registered on the real container before any bundle's build() runs, but each
        // extension's own load() runs against a temporary, per-extension container copy where
        // sibling extensions never show up in hasExtension().
        $container->setParameter(
            'ibexa_test_core.has_doctrine_schema_bundle',
            $container->hasExtension('ibexa_doctrine_schema')
        );
    }
}
