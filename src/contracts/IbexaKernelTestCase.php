<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @experimental
 */
abstract class IbexaKernelTestCase extends KernelTestCase
{
    private IbexaTestCoreInterface $ibexaCore;

    protected function getIbexaTestCore(): IbexaTestCoreInterface
    {
        return $this->ibexaCore ??= new IbexaTestCore(self::getContainer());
    }
}
