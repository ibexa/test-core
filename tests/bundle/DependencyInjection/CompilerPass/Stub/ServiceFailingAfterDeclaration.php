<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub;

/**
 * Declares itself, then fails — so the class is loaded by the time the Error surfaces. The message
 * is not exposed as a constant: reading one would load the class and set the throw off early.
 */
final class ServiceFailingAfterDeclaration
{
}

throw new \Error('failure unrelated to loading');
