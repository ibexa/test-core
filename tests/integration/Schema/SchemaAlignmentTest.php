<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Test\Core\Schema;

use Ibexa\Contracts\Test\Core\Schema\AbstractSchemaAlignmentTestCase;

/**
 * The suite's bootstrap installs the schema SchemaBuilderEvent declares, so this passes by
 * construction, like it does on any package's legacy install path.
 *
 * @group integration
 *
 * @covers \Ibexa\Contracts\Test\Core\Schema\AbstractSchemaAlignmentTestCase
 */
final class SchemaAlignmentTest extends AbstractSchemaAlignmentTestCase
{
}
