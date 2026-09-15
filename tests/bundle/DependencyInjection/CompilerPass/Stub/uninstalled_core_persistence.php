<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\CorePersistence\Gateway;

/**
 * Stands in for ibexa/core-persistence, which is not a dependency of this package. Without it
 * AbstractDoctrineDatabase does not exist, PersistenceCheckCompilerPass returns early at its own
 * class_exists() guard, and there is nothing left to test.
 *
 * Only the class name matters here — the pass compares service classes against it and never
 * instantiates anything, so no members are reproduced. The file is named in snake case, outside
 * the PSR-4 layout, because it declares a class in someone else's namespace: it is loaded
 * deliberately by the test rather than by the autoloader, and only when the real package is
 * absent.
 */
abstract class AbstractDoctrineDatabase
{
}
