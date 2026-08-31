<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\DefaultSchemaFilesProvider;
use function Ibexa\PolyfillPhp82\iterator_to_array;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\DefaultSchemaFilesProvider
 */
final class DefaultSchemaFilesProviderTest extends TestCase
{
    public function testYieldsSchemaFileResolvedThroughTheKernel(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->expects(self::once())
            ->method('locateResource')
            ->with('@IbexaCoreBundle/Resources/config/storage/legacy/schema.yaml')
            ->willReturn('/resolved/path/schema.yaml');

        $provider = new DefaultSchemaFilesProvider($kernel);

        self::assertSame(['/resolved/path/schema.yaml'], iterator_to_array($provider->getSchemaFiles()));
    }
}
