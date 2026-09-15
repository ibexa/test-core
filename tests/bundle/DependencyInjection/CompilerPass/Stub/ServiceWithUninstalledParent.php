<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub;

use Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub\UninstalledDependency\ParentFromUninstalledDependency;

/**
 * A service class that cannot be loaded, because its parent comes from a dependency that is not
 * installed. There is deliberately no file declaring {@see ParentFromUninstalledDependency}, so
 * resolving this class name raises an Error naming the parent.
 *
 * This is api-platform/core's GraphQL scalar types in miniature: they extend
 * GraphQL\Type\Definition\ScalarType from webonyx/graphql-php, which is only installed when
 * GraphQL support is actually in use. A made-up namespace is used rather than the real one so the
 * test cannot start passing for the wrong reason if webonyx/graphql-php is ever installed here.
 *
 * The unknown parent is the entire point of this fixture. PHPStan reports an extends clause
 * outside the reach of @phpstan-ignore, so this one file is listed under excludePaths.analyse in
 * phpstan.neon instead; it is still scanned, so the class itself stays known everywhere else.
 */
final class ServiceWithUninstalledParent extends ParentFromUninstalledDependency
{
}
