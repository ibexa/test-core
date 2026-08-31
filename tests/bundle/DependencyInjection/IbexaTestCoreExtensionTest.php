<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Test\Core\DependencyInjection;

use Ibexa\Bundle\Test\Core\DependencyInjection\IbexaTestCoreExtension;
use Ibexa\Test\Core\Bootstrapper\HooksExecutor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @covers \Ibexa\Bundle\Test\Core\DependencyInjection\IbexaTestCoreExtension
 */
final class IbexaTestCoreExtensionTest extends TestCase
{
    public function testRegistersNoServicesOutsideTheTestEnvironment(): void
    {
        $container = $this->containerForEnvironment('dev');

        (new IbexaTestCoreExtension())->load([], $container);

        self::assertFalse($container->hasDefinition(HooksExecutor::class));
    }

    public function testRegistersServicesInTheTestEnvironment(): void
    {
        $container = $this->containerForEnvironment('test');

        (new IbexaTestCoreExtension())->load([], $container);

        self::assertTrue($container->hasDefinition(HooksExecutor::class));
    }

    private function containerForEnvironment(string $environment): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', $environment);

        return $container;
    }
}
