<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\HookInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;

final class HooksExecutor implements HooksExecutorInterface
{
    /**
     * @var iterable<HookInterface>
     */
    private iterable $hooks;

    /**
     * @param iterable<HookInterface> $hooks
     */
    public function __construct(iterable $hooks)
    {
        $this->hooks = $hooks;
    }

    public function execute(array $options = []): void
    {
        foreach ($this->hooks as $hook) {
            $hook($options);
        }
    }
}
