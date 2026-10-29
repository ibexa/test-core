<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Test\Core;

use Ibexa\Bundle\RepositoryInstaller\Event\Subscriber\BuildSchemaSubscriber;
use Ibexa\Bundle\Test\Core\IbexaTestCoreBundle;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class TestKernel extends IbexaTestKernel
{
    public function registerBundles(): iterable
    {
        yield from parent::registerBundles();

        yield new IbexaTestCoreBundle();
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);

        // Adds the schema alignment cases to the schema, the way a package adds its schema.yaml
        $loader->load(static function (ContainerBuilder $container): void {
            $container
                ->register('ibexa.test.core.schema_alignment_cases_subscriber', BuildSchemaSubscriber::class)
                ->setArguments([__DIR__ . '/Schema/_fixtures/schema.yaml'])
                ->addTag('kernel.event_subscriber');
        });
    }
}
