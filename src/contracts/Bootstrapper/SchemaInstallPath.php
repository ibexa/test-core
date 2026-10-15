<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Bundle\RepositoryInstaller\Bootstrapper\DoctrineMigrationsSchemaHook;

/**
 * Selects which of the two coexisting schema install paths a bootstrap exercises: the
 * SchemaBuilderEvent one, through {@see DatabaseSchemaHook}, or the Doctrine Migrations one, through
 * ibexa/core's {@see DoctrineMigrationsSchemaHook}. Exactly one of them installs the schema.
 *
 * Which path a run takes is up to the package, usually an environment variable its CI sets:
 *
 * ```php
 * $schemaBuilderEventEnabled = getenv('IBEXA_TEST_SCHEMA_BUILDER_EVENT_ENABLED') !== '0';
 *
 * (new Bootstrapper())->bootstrap(null, SchemaInstallPath::getBootstrapperOptions($schemaBuilderEventEnabled));
 * ```
 */
final class SchemaInstallPath
{
    /**
     * @return array<class-string<HookInterface>, array<string, bool>>
     */
    public static function getBootstrapperOptions(bool $schemaBuilderEventEnabled): array
    {
        return [
            DatabaseSchemaHook::class => [DatabaseSchemaHook::OPTION_LOAD_SCHEMA => $schemaBuilderEventEnabled],
            DoctrineMigrationsSchemaHook::class => [
                DoctrineMigrationsSchemaHook::OPTION_INSTALL_SCHEMA => !$schemaBuilderEventEnabled,
            ],
        ];
    }
}
