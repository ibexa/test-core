<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Test\Core\DependencyInjection\CompilerPass;

use Ibexa\Bundle\RepositoryInstaller\Event\Subscriber\BuildSchemaSubscriber;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\DatabaseSchemaHook;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Drops built-in hooks whose collaborators the consuming kernel doesn't provide, so registering
 * this bundle never costs a kernel more than the hooks it can actually satisfy.
 */
final class RemoveUnsatisfiableHooksPass implements CompilerPassInterface
{
    /**
     * @var array<class-string, list<string>> hook service id => service ids it cannot work without
     */
    private const HOOK_REQUIREMENTS = [
        DatabaseSchemaHook::class => [
            SchemaBuilderInterface::class,
            // core's own schema contribution; without it the event yields every other package's
            // tables but none of core's
            BuildSchemaSubscriber::class,
        ],
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::HOOK_REQUIREMENTS as $hookId => $requiredServiceIds) {
            if (!$container->hasDefinition($hookId)) {
                continue;
            }

            foreach ($requiredServiceIds as $serviceId) {
                if (!$container->has($serviceId)) {
                    $container->removeDefinition($hookId);

                    break;
                }
            }
        }
    }
}
