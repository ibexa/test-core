<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub;

/**
 * A service class that declares itself and only then fails. By the time the Error surfaces the
 * class is loaded, which is how the compiler pass tells this case apart from a class that could
 * not be loaded at all — the former is rethrown, the latter skipped.
 *
 * The failure message is repeated as a literal in the test rather than exposed as a constant here:
 * reading a constant off this class would load it, and the throw below would then go off before
 * the test under way had a chance to set anything up.
 */
final class ServiceFailingAfterDeclaration
{
}

throw new \Error('failure unrelated to loading');
