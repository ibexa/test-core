<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\HookInterface;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use Ibexa\Tests\Core\Repository\LegacySchemaImporter;

/**
 * Imports the legacy raw-SQL schema files exposed by the test kernel via {@see IbexaTestKernel::getSchemaFiles()}.
 *
 * Enabled by default; pass `['schema' => false]` as bootstrap options to skip it.
 */
final class SchemaHook implements HookInterface
{
    private IbexaTestKernel $kernel;
    private LegacySchemaImporter $schemaImporter;

    public function __construct(
        IbexaTestKernel $kernel,
        LegacySchemaImporter $schemaImporter
    ) {
        $this->kernel = $kernel;
        $this->schemaImporter = $schemaImporter;
    }

    public function __invoke(array $options): void
    {
        if (!($options['schema'] ?? true)) {
            return;
        }

        foreach ($this->kernel->getSchemaFiles() as $file) {
            $this->schemaImporter->importSchema($file);
        }
    }
}
