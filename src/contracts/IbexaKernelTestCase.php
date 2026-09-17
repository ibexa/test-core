<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core;

use Ibexa\Contracts\Core\Test\IbexaTestKernelInterface;
use Ibexa\Contracts\Test\Core\Bootstrapper\SymfonyErrorHandlerRestorer;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @experimental
 */
abstract class IbexaKernelTestCase extends KernelTestCase
{
    private IbexaTestCoreInterface $ibexaCore;

    protected function getIbexaTestCore(): IbexaTestCoreInterface
    {
        if (!self::$booted) {
            self::bootKernel();
        }

        if (!isset($this->ibexaCore)) {
            if (!self::$kernel instanceof IbexaTestKernelInterface) {
                throw new \LogicException(sprintf(
                    '%s requires %s as an argument, but received %s. Ensure that KERNEL_CLASS env variable is set properly.',
                    IbexaTestCore::class,
                    IbexaTestKernelInterface::class,
                    get_debug_type(self::$kernel),
                ));
            }
            $this->ibexaCore = new IbexaTestCore(self::getContainer(), self::$kernel);
        }

        return $this->ibexaCore;
    }

    /**
     * @param array<string, mixed> $options
     *
     * FrameworkBundle::boot() (invoked by the parent's kernel boot) may leave Symfony's own
     * ErrorHandler on top of the handler stack, which stops PHPUnit's own error handler from
     * installing itself - see {@see SymfonyErrorHandlerRestorer} for the full explanation.
     */
    protected static function bootKernel(array $options = []): KernelInterface
    {
        $kernel = parent::bootKernel($options);

        SymfonyErrorHandlerRestorer::restoreIfSymfonyHandlerIsOnTop();

        return $kernel;
    }
}
