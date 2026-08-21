<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\HookInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class HooksExecutor implements HooksExecutorInterface
{
    /**
     * @var iterable<string, HookInterface>
     */
    private iterable $hooks;

    /**
     * @param iterable<string, HookInterface> $hooks keyed by each hook's own service id, in
     *                                                tag-priority order
     */
    public function __construct(iterable $hooks)
    {
        $this->hooks = $hooks;
    }

    public function execute(array $options = []): void
    {
        foreach ($this->hooks as $hookId => $hook) {
            $resolver = new OptionsResolver();
            $hook->configureOptions($resolver);

            $hook($resolver->resolve($options[$hookId] ?? []));
        }
    }
}
