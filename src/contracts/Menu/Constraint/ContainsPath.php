<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Menu\Constraint;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Constraint\Constraint;

final class ContainsPath extends Constraint
{
    /** @var string[] */
    private array $path;

    /**
     * @param string[] $path
     */
    public function __construct(array $path)
    {
        $this->path = $path;
    }

    protected function matches($other): bool
    {
        assert($other instanceof ItemInterface);

        $item = $other;
        foreach ($this->path as $name) {
            $item = $item->getChild($name);

            if ($item === null) {
                return false;
            }
        }

        return true;
    }

    public function toString(): string
    {
        return sprintf('menu contains %s path', implode('/', $this->path));
    }
}
