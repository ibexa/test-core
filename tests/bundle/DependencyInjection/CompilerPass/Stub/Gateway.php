<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub;

use Ibexa\Contracts\CorePersistence\Gateway\AbstractDoctrineDatabase;

/**
 * A service class the compiler pass does care about: a Doctrine database gateway, which must take
 * the ibexa.persistence.connection service as its connection.
 *
 * Declared abstract on purpose. The real AbstractDoctrineDatabase declares abstract members of its
 * own, so a concrete subclass would have to implement them and would fail to load the moment
 * ibexa/core-persistence were actually installed alongside {@see uninstalled_core_persistence.php}.
 * The pass only compares class names and never instantiates, so being abstract costs nothing.
 */
abstract class Gateway extends AbstractDoctrineDatabase
{
}
