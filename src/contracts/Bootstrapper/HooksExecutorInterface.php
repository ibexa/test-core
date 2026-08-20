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
     * Runs every registered {@see HookInterface}, in tag-priority order.
     *
     * @param array<string, mixed> $options
     */
    public function execute(array $options = []): void;
}
