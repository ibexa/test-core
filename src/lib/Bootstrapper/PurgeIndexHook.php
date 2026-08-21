<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Search\VersatileHandler;
use Ibexa\Contracts\Test\Core\Bootstrapper\HookInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Purges the search index.
 *
 * Disabled by default; pass `['purge_index' => true]` as this hook's own options (keyed by its own
 * service id in the bootstrap options array) to enable it.
 */
final class PurgeIndexHook implements HookInterface
{
    private VersatileHandler $handler;

    public function __construct(VersatileHandler $handler)
    {
        $this->handler = $handler;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['purge_index' => false]);
        $resolver->setAllowedTypes('purge_index', 'bool');
    }

    public function __invoke(array $options): void
    {
        if (!$options['purge_index']) {
            return;
        }

        $this->handler->purgeIndex();
    }
}
