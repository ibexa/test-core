<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Search\VersatileHandler;
use Ibexa\Contracts\Test\Core\Bootstrapper\HookInterface;

/**
 * Purges the search index.
 *
 * Disabled by default; pass `['purge_index' => true]` as bootstrap options to enable it.
 */
final class PurgeIndexHook implements HookInterface
{
    private VersatileHandler $handler;

    public function __construct(VersatileHandler $handler)
    {
        $this->handler = $handler;
    }

    public function __invoke(array $options): void
    {
        if (!($options['purge_index'] ?? false)) {
            return;
        }

        $this->handler->purgeIndex();
    }
}
