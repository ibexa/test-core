<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\IbexaTestKernel;

/**
 * @experimental
 *
 * @internal
 *
 * Prepares the database {@see Bootstrapper::bootstrap()} imports schema and fixtures into.
 */
interface DatabasePreparerInterface
{
    /**
     * @throws \Exception command failures propagate as-is
     */
    public function prepareDatabase(IbexaTestKernel $kernel, bool $runSchemaUpdate): void;
}
