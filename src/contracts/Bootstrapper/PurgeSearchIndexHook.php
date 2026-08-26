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
 * Purges the search index, meant to run after every other hook.
 *
 * Disabled by default; pass `[self::OPTION_PURGE_INDEX => true]` as this hook's own options (keyed
 * by its own service id in the bootstrap options array) to enable it.
 */
final class PurgeSearchIndexHook implements HookInterface
{
    /**
     * Fixed tag priority this hook is registered at — deliberately negative, not just "lower than
     * the other built-in hooks": Symfony's tagged_iterator() defaults an untagged-with-priority
     * service to priority 0, so a downstream hook that doesn't bother declaring one would still run
     * before this one purges the index if this were 0 or higher.
     */
    public const PRIORITY = -100;

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
