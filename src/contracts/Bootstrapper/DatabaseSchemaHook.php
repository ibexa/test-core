<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Tests\Core\Repository\LegacySchemaImporter;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Imports the legacy raw-SQL schema files exposed by {@see SchemaFilesProviderInterface}.
 *
 * Enabled by default; pass `[self::OPTION_LOAD_SCHEMA => false]` as this hook's own options (keyed
 * by its own service id in the bootstrap options array) to skip it.
 */
final class DatabaseSchemaHook implements HookInterface
{
    /**
     * Fixed tag priority this hook is registered at — runs first, before any fixture/migration
     * import needs the schema to exist.
     */
    public const PRIORITY = 1000;

    public const OPTION_LOAD_SCHEMA = 'load_schema';

    private SchemaFilesProviderInterface $provider;

    private LegacySchemaImporter $schemaImporter;

    public function __construct(
        SchemaFilesProviderInterface $provider,
        LegacySchemaImporter $schemaImporter
    ) {
        $this->provider = $provider;
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

        foreach ($this->provider->getSchemaFiles() ?? [] as $file) {
            $this->schemaImporter->importSchema($file);
        }
    }
}
