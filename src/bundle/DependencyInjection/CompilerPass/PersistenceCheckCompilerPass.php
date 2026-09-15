<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Bundle\Test\Core\DependencyInjection\CompilerPass;

use Ibexa\Contracts\CorePersistence\Gateway\AbstractDoctrineDatabase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Reference;

final class PersistenceCheckCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!class_exists(AbstractDoctrineDatabase::class)) {
            return;
        }

        foreach ($container->getDefinitions() as $definitionId => $definition) {
            $class = $definition->getClass();

            if ($class === null) {
                continue;
            }

            try {
                $isDoctrineDatabaseGateway = is_a($class, AbstractDoctrineDatabase::class, true);
            } catch (\Error $e) {
                // Resolving the name autoloads the class, which fails outright when it extends or
                // implements something from a dependency that is not installed — for instance
                // api-platform/core's GraphQL scalar types, which extend a webonyx/graphql-php
                // class that is only present when GraphQL support is actually in use.
                //
                // Only a class that failed to load is tolerated here. If it did load, the error
                // came from somewhere else and is not ours to swallow.
                if (class_exists($class, false) || interface_exists($class, false)) {
                    throw $e;
                }

                continue;
            }

            if (!$isDoctrineDatabaseGateway) {
                continue;
            }

            $argument = (string)$this->getConnectionArgument($definition);
            if ($argument !== 'ibexa.persistence.connection') {
                throw new \LogicException(sprintf(
                    'Service definition "%s" contains reference to "%s" as connection argument. '
                    . 'Expected "%s". This will cause issues in multi-repository setups.',
                    $definitionId,
                    $argument,
                    'ibexa.persistence.connection',
                ));
            }
        }
    }

    private function getConnectionArgument(Definition $definition): Reference
    {
        try {
            return $definition->getArgument('$connection');
        } catch (OutOfBoundsException $e) {
            return $definition->getArgument(0);
        }
    }
}
