<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\IbexaTestKernelInterface;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The built-in legacy schema file {@see IbexaTestKernel} contributes by
 * default. Deliberately not tagged as a {@see SchemaFilesProviderInterface} — the kernel-method
 * fallback ({@see SchemaFilesKernelMethodProvider}) already always wins for every kernel, since
 * {@see IbexaTestKernelInterface} makes `getSchemaFiles()` mandatory. This
 * class exists purely so another provider can constructor-inject it directly to compose with the
 * built-in default without going through a Kernel method.
 */
final class DefaultSchemaFilesProvider
{
    private KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    /**
     * @return iterable<string>
     */
    public function getSchemaFiles(): iterable
    {
        yield $this->kernel->locateResource('@IbexaCoreBundle/Resources/config/storage/legacy/schema.yaml');
    }
}
