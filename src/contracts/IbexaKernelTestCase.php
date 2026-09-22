<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core;

use Ibexa\Contracts\Core\Test\IbexaTestKernelInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
}
