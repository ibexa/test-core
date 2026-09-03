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
 */
interface HooksExecutorInterface
{
    /**
     * Declares, for every registered {@see HookInterface}, an option keyed by that hook's own
     * service id — resolved against whatever that hook itself declares in
     * {@see HookInterface::configureOptions()} — on the given resolver. Meant to be called on a
     * resolver the caller also defines its own options on (e.g. Bootstrapper's own
     * "schema_update"), so a single {@see OptionsResolver::resolve()} call validates the whole
     * top-level options array at once and rejects any key that isn't recognized.
     */
    public function configureOptions(OptionsResolver $resolver): void;

    /**
     * Runs every registered {@see HookInterface}, in tag-priority order, passing each one its own
     * sub-array of $options (found under a top-level key equal to that hook's own service id, or an
     * empty array if that key is absent).
     *
     * @param array<string, mixed> $options top-level array, already resolved against a resolver
     *                                       this class's own {@see self::configureOptions()} was
     *                                       called on
     */
    public function execute(array $options = []): void;
}
