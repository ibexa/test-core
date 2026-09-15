<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub;

use Ibexa\Contracts\CorePersistence\Gateway\AbstractDoctrineDatabase;

/**
 * Abstract because AbstractDoctrineDatabase declares abstract members the pass does not need.
 *
 * @extends AbstractDoctrineDatabase<array<string, mixed>>
 */
abstract class Gateway extends AbstractDoctrineDatabase
{
}
