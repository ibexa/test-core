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

class PersistenceCheckCompilerPass implements CompilerPassInterface
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

            if (!is_a($class, AbstractDoctrineDatabase::class, true)) {
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
