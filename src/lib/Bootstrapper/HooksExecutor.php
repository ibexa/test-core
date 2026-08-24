<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\HookInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface;
use Symfony\Component\OptionsResolver\Options;
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        foreach ($this->hooks as $hookId => $hook) {
            $resolver->define($hookId)
                ->default([])
                ->allowedTypes('array')
                ->normalize(static function (Options $options, array $value) use ($hook): array {
                    $hookResolver = new OptionsResolver();
                    $hook->configureOptions($hookResolver);

                    return $hookResolver->resolve($value);
                });
        }
    }

    public function execute(array $options = []): void
    {
        foreach ($this->hooks as $hookId => $hook) {
            $hook($options[$hookId] ?? []);
        }
    }
}
