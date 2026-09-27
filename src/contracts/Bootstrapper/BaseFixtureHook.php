<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\Persistence\Fixture\FixtureImporter;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Imports the baseline repository content every kernel starts from, as defined by
 * {@see DefaultFixtureProvider}.
 *
 * Kept apart from {@see FixtureHook} because the two answer different questions. The provider
 * chain behind `FixtureHook` is a replacement mechanism - one provider wins and decides what a
 * package contributes - whereas the baseline is an "always", independent of that choice. Routing
 * it through the chain made it something a package could lose by accident: a provider composing
 * the baseline into its own output could dissolve it (unwrapping via `Fixture::load()` and
 * re-merging), and configuring `ibexa.test.fixture_files` would outrank the kernel-method provider
 * and drop it outright.
 *
 * It also has a second source. When the Doctrine Migrations install path runs, ibexa/core's
 * ImportDataMigration inserts the same content from its import-data SQL, so this hook is what gets
 * switched off there - see {@see self::OPTION_LOAD_BASE_FIXTURE}.
 *
 * Requires `FixtureImporter`; removed from the container when it is missing, by
 * {@see \Ibexa\Bundle\Test\Core\DependencyInjection\CompilerPass\RemoveUnsatisfiableHooksPass}.
 */
final class BaseFixtureHook implements HookInterface
{
    /**
     * Fixed tag priority this hook is registered at - runs after the schema is in place
     * ({@see DatabaseSchemaHook} at 1000, ibexa/core's DoctrineMigrationsSchemaHook at 990) and
     * before the package fixtures layered on top of the baseline ({@see FixtureHook} at 900).
     */
    public const PRIORITY = 950;

    /**
     * Enabled by default - the baseline is what every kernel starts from.
     *
     * Pass `[self::OPTION_LOAD_BASE_FIXTURE => false]` as this hook's own options (keyed by its own
     * service id in the bootstrap options array) on the Doctrine Migrations install path, where
     * ibexa/core's ImportDataMigration has already inserted the same content. Both populate the
     * same tables with the same primary keys, so importing on top of the migration's output
     * collides.
     */
    public const OPTION_LOAD_BASE_FIXTURE = 'load_base_fixture';

    private DefaultFixtureProvider $provider;

    private FixtureImporter $fixtureImporter;

    public function __construct(
        DefaultFixtureProvider $provider,
        FixtureImporter $fixtureImporter
    ) {
        $this->provider = $provider;
        $this->fixtureImporter = $fixtureImporter;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define(self::OPTION_LOAD_BASE_FIXTURE)
            ->default(true)
            ->allowedTypes('bool');
    }

    public function __invoke(array $options): void
    {
        if (!$options[self::OPTION_LOAD_BASE_FIXTURE]) {
            return;
        }

        foreach ($this->provider->getFixtures() as $fixture) {
            $this->fixtureImporter->import($fixture);
        }
    }
}
