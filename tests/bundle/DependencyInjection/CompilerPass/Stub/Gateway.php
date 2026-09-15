<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub;

use Ibexa\Contracts\CorePersistence\Gateway\AbstractDoctrineDatabase;

/**
 * Abstract on purpose: the real AbstractDoctrineDatabase declares abstract members, so a concrete
 * subclass would stop loading if ibexa/core-persistence were ever installed here.
 */
abstract class Gateway extends AbstractDoctrineDatabase
{
}
