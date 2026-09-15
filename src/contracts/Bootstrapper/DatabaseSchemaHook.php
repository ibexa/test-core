<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\DoctrineSchema\Builder\SchemaApplierInterface;
use Ibexa\Contracts\DoctrineSchema\Builder\SchemaBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Installs the database schema built by {@see SchemaBuilderInterface::buildSchema()}, i.e. from
 * every registered bundle's own SchemaBuilderEvent subscriber. Requires `DoctrineSchemaBundle` and
 * `IbexaRepositoryInstallerBundle`; removed from the container when either is missing, by
 * {@see \Ibexa\Bundle\Test\Core\DependencyInjection\CompilerPass\RemoveUnsatisfiableHooksPass}.
 *
 * Enabled by default; pass `[self::OPTION_LOAD_SCHEMA => false]` as this hook's own options to
 * skip it.
 */
final class DatabaseSchemaHook implements HookInterface
{
    /**
     * Fixed tag priority this hook is registered at — runs first, before any fixture/migration
     * import needs the schema to exist.
     */
    public const PRIORITY = 1000;

    public const OPTION_LOAD_SCHEMA = 'load_schema';

    private SchemaBuilderInterface $schemaBuilder;

    private SchemaApplierInterface $schemaApplier;

    public function __construct(SchemaBuilderInterface $schemaBuilder, SchemaApplierInterface $schemaApplier)
    {
        $this->schemaBuilder = $schemaBuilder;
        $this->schemaApplier = $schemaApplier;
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

        // the test database is always freshly created, so there is nothing to drop first
        $this->schemaApplier->applySchema($this->schemaBuilder->buildSchema());
    }
}
