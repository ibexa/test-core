<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Schema\Stub;

use Ibexa\Contracts\Test\Core\Schema\AbstractSchemaParityTestCase;
use Ibexa\Contracts\Test\Core\Schema\ReleasedSchema;

/**
 * A package's schema parity test, as a package would declare it with snapshot files.
 * Not named *Test, so PHPUnit doesn't collect it on its own.
 */
final class SchemaParityTestStub extends AbstractSchemaParityTestCase
{
    protected static function getOwnTableNames(): array
    {
        return ['stub_table'];
    }

    protected static function getReleasedSchemas(): iterable
    {
        yield new ReleasedSchema('v4.6.0..v4.6.3', "tables:\n    stub_table:\n        id:\n            id: { type: integer, nullable: false }\n");
        yield new ReleasedSchema('v4.6.4', "tables:\n    stub_table:\n        id:\n            id: { type: bigint, nullable: false }\n");
    }
}
