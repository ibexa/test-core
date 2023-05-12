<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Test\Core;

use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;

/**
 * @group integration
 *
 * @coversNothing
 */
final class SampleTest extends IbexaKernelTestCase
{
    public function testCompilesSuccessfully(): void
    {
        // do nothing, container compiled via setUp() call
    }
}
