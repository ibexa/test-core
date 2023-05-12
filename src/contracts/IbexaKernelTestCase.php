<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core;

use Ibexa\Contracts\Core\Test\IbexaTestKernelInterface;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @experimental
 */
abstract class IbexaKernelTestCase extends KernelTestCase
{
    protected IbexaTestCoreInterface $ibexaCore;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        /** @var \Symfony\Component\DependencyInjection\ContainerInterface $container */
        $container = static::$kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(IbexaTestKernelInterface::class, $kernel);
        $this->ibexaCore = new IbexaTestCore($container, $kernel);
    }

    protected static function getKernelClass(): string
    {
        try {
            return parent::getKernelClass();
        } catch (LogicException $e) {
            return IbexaTestKernel::class;
        }
    }
}
