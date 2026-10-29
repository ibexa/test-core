<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Test\Core\Schema;

use Ibexa\Contracts\Test\Core\Schema\AbstractSchemaAlignmentTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * The suite's bootstrap installs the schema SchemaBuilderEvent declares, so this passes by
 * construction, like it does on any package's legacy install path. Next to core's tables, the
 * schema has those _fixtures/schema.yaml declares for each case the comparison normalizes.
 */
#[Group('integration')]
#[CoversClass(AbstractSchemaAlignmentTestCase::class)]
final class SchemaAlignmentTest extends AbstractSchemaAlignmentTestCase {}
