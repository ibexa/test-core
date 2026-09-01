<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Search\VersatileHandler;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Shared implementation behind {@see PurgeSearchIndexHook} and {@see PurgeIndexAfterFixturesHook} —
 * the two differ only in their fixed tag priority (and therefore when in the bootstrap sequence
 * they run); both purge the search index the same way, disabled by default via the same option.
 *
 * @internal not part of the public contract — the two concrete hooks are what consumers reference.
 */
abstract class AbstractPurgeIndexHook implements HookInterface
{
    public const OPTION_PURGE_INDEX = 'purge_index';

    private VersatileHandler $handler;

    public function __construct(VersatileHandler $handler)
    {
        $this->handler = $handler;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define(self::OPTION_PURGE_INDEX)
            ->default(false)
            ->allowedTypes('bool');
    }

    public function __invoke(array $options): void
    {
        if (!$options[self::OPTION_PURGE_INDEX]) {
            return;
        }

        $this->handler->purgeIndex();
    }
}
