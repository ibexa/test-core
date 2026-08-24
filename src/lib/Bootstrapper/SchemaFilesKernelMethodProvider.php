<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\SchemaFilesProviderInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Reads the schema file list off the kernel's own `getSchemaFiles()` method, if it declares one.
 *
 * Typed against the generic {@see KernelInterface}, not {@see \Ibexa\Contracts\Core\Test\IbexaTestKernelInterface}
 * — kernels are expected to move away from this method over time, so this provider treats it the
 * same way {@see \Ibexa\Contracts\Migration\Bootstrapper\MigrationHook} already treats its own
 * kernel method: duck-typed, absence isn't an error.
 */
final class SchemaFilesKernelMethodProvider implements SchemaFilesProviderInterface
{
    private KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function getSchemaFiles(): ?iterable
    {
        if (!method_exists($this->kernel, 'getSchemaFiles')) {
            return null;
        }

        return $this->kernel->getSchemaFiles();
    }
}
