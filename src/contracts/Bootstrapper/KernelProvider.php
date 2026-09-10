<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use LogicException;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
final class KernelProvider implements KernelProviderInterface
{
    public function getKernel(?string $kernelClass): KernelInterface
    {
        $kernelClass ??= $_ENV['KERNEL_CLASS'] ?? $_SERVER['KERNEL_CLASS'] ?? null;
        if ($kernelClass === null || !is_a($kernelClass, KernelInterface::class, true)) {
            throw new LogicException(sprintf(
                'The kernel class "%s" must implement "%s". Ensure that the KERNEL_CLASS environment variable is set to a valid test kernel class.',
                $kernelClass ?? 'null',
                KernelInterface::class,
            ));
        }

        $kernel = new $kernelClass('test', true);
        $kernel->boot();

        return $kernel;
    }
}
