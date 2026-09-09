<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @experimental
 *
 * @internal
 *
 * Resolves and boots the test kernel {@see Bootstrapper::bootstrap()} operates on.
 */
interface KernelProviderInterface
{
    /**
     * @throws \LogicException if $kernelClass (or its env/server fallback) doesn't name a
     *                          bootable {@see KernelInterface} implementation
     */
    public function getKernel(?string $kernelClass): KernelInterface;
}
