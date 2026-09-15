<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\CorePersistence\Gateway;

/**
 * Stands in for ibexa/core-persistence, which is not a dependency here — without it the pass
 * returns early at its own class_exists() guard. Outside the PSR-4 layout because the namespace is
 * someone else's, so the test requires it explicitly rather than relying on the autoloader.
 */
abstract class AbstractDoctrineDatabase
{
}
