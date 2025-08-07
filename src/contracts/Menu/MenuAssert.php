<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Menu;

use Ibexa\Contracts\Test\Core\Menu\Constraint\ContainsPath;
use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\LogicalNot;

final class MenuAssert
{
    /**
     * @param string[] $path
     */
    public static function assertMenuContainsPath(array $path, ItemInterface $menu, string $message = ''): void
    {
        Assert::assertThat($menu, new ContainsPath($path), $message);
    }

    /**
     * @param string[] $path
     */
    public static function assertMenuNotContainsPath(array $path, ItemInterface $menu, string $message = ''): void
    {
        Assert::assertThat($menu, new LogicalNot(new ContainsPath($path)), $message);
    }
}
