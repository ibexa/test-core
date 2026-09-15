<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub;

use Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub\UninstalledDependency\ParentFromUninstalledDependency;

/**
 * The parent is deliberately never declared, so this class cannot be loaded. @phpstan-ignore does
 * not reach an extends clause, hence the excludePaths.analyse entry in phpstan.neon.
 */
final class ServiceWithUninstalledParent extends ParentFromUninstalledDependency {}
