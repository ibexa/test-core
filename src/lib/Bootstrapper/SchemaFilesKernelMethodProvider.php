<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\IbexaTestKernelInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\SchemaFilesProviderInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Reads the schema file list off the kernel's own `getSchemaFiles()` method, if it declares one.
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
        if (!$this->kernel instanceof IbexaTestKernelInterface) {
            return null;
        }

        return $this->kernel->getSchemaFiles();
    }
}
