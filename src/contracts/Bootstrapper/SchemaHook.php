<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\IbexaTestKernelInterface;
use Ibexa\Tests\Core\Repository\LegacySchemaImporter;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Imports the legacy raw-SQL schema files exposed by the test kernel via
 * {@see IbexaTestKernelInterface::getSchemaFiles()}.
 *
 * Typed against the interface, not the concrete Ibexa\Contracts\Test\Core\IbexaTestKernel: Symfony's
 * autowiring only resolves the synthetic "kernel" service by the interfaces it implements, not by
 * intermediate parent classes.
 *
 * Enabled by default; pass `[self::OPTION_LOAD_SCHEMA => false]` as this hook's own options (keyed
 * by its own service id in the bootstrap options array) to skip it.
 */
final class SchemaHook implements HookInterface
{
    public const OPTION_LOAD_SCHEMA = 'load_schema';

    private IbexaTestKernelInterface $kernel;

    private LegacySchemaImporter $schemaImporter;

    public function __construct(
        IbexaTestKernelInterface $kernel,
        LegacySchemaImporter $schemaImporter
    ) {
        $this->kernel = $kernel;
        $this->schemaImporter = $schemaImporter;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define(self::OPTION_LOAD_SCHEMA)
            ->default(true)
            ->allowedTypes('bool');
    }

    public function __invoke(array $options): void
    {
        if (!$options[self::OPTION_LOAD_SCHEMA]) {
            return;
        }

        foreach ($this->kernel->getSchemaFiles() as $file) {
            $this->schemaImporter->importSchema($file);
        }
    }
}
