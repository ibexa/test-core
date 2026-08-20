<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

/**
 * @experimental
 *
 * A single step of early test environment initialization (e.g. importing schema, loading fixtures).
 *
 * Register an implementation as a service tagged "ibexa.test.bootstrapper.hook" (with an optional
 * "priority" tag attribute controlling execution order) to have it picked up by
 * {@see \Ibexa\Contracts\Test\Core\Bootstrapper\HooksExecutorInterface} automatically — no changes
 * to ibexa/test-core are needed for a downstream bundle to contribute its own hook.
 */
interface HookInterface
{
    /**
     * @param array<string, mixed> $options the options passed to the bootstrapper; a hook should
     *                                       only read the option(s) it cares about and ignore the rest
     */
    public function __invoke(array $options): void;
}
