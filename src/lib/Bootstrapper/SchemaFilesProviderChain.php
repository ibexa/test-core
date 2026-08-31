<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\SchemaFilesProviderInterface;

/**
 * Tries every registered {@see SchemaFilesProviderInterface}, in priority order, and returns the
 * first one that actually has something to say. A provider returning null is skipped (it has
 * nothing to contribute); a provider returning an iterable — even an empty one — wins immediately,
 * since that's a deliberate, final answer, not an absence of one.
 */
final class SchemaFilesProviderChain implements SchemaFilesProviderInterface
{
    /**
     * @var iterable<SchemaFilesProviderInterface>
     */
    private iterable $providers;

    /**
     * @param iterable<SchemaFilesProviderInterface> $providers priority-sorted
     */
    public function __construct(iterable $providers)
    {
        $this->providers = $providers;
    }

    public function getSchemaFiles(): ?iterable
    {
        foreach ($this->providers as $provider) {
            $schemaFiles = $provider->getSchemaFiles();
            if ($schemaFiles !== null) {
                return $schemaFiles;
            }
        }

        return null;
    }
}
