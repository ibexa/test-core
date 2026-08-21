<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

/**
 * @experimental
 */
interface HooksExecutorInterface
{
    /**
     * Runs every registered {@see HookInterface}, in tag-priority order. Each hook's own sub-array
     * of $options — found under a top-level key equal to that hook's own service id, or an empty
     * array if that key is absent — is resolved against the OptionsResolver that hook declares in
     * {@see HookInterface::configureOptions()} before being passed to it.
     *
     * @param array<string, mixed> $options top-level array; each key is either a hook's own service
     *                                       id (mapping to that hook's own options sub-array) or an
     *                                       option belonging to the caller itself (e.g.
     *                                       Bootstrapper's own "schema_update")
     */
    public function execute(array $options = []): void;
}
