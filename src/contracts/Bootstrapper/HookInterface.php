<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Symfony\Component\OptionsResolver\OptionsResolver;

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
     * Declares this hook's own options — names, defaults, allowed types — via OptionsResolver.
     * Called with a fresh resolver once per {@see HooksExecutorInterface::execute()} run, before
     * this hook's own sub-array of options (keyed by this hook's own service id in the top-level
     * array passed to execute()) is resolved against it.
     */
    public function configureOptions(OptionsResolver $resolver): void;

    /**
     * @param array<string, mixed> $options this hook's own options, already resolved against
     *                                       configureOptions()
     */
    public function __invoke(array $options): void;
}
