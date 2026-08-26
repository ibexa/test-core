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
 * Imports the fixtures exposed by {@see FixtureProviderInterface}.
 *
 * Enabled by default; pass `[self::OPTION_LOAD_FIXTURES => false]` as this hook's own options (keyed
 * by its own service id in the bootstrap options array) to skip it.
 */
final class FixtureHook implements HookInterface
{
    /**
     * Fixed tag priority this hook is registered at — runs after {@see DatabaseSchemaHook} (1000),
     * before ibexa/migrations' MigrationHook (500), if present.
     */
    public const PRIORITY = 900;

    public const OPTION_LOAD_FIXTURES = 'load_fixtures';

    /**
     * @deprecated a bridge for packages migrating off {@see \Ibexa\Contracts\Core\Test\IbexaKernelTestTrait::postLoadFixtures()}
     *             or {@see \Ibexa\Contracts\Test\Core\IbexaTestCore::loadFixtures()}'s own callable
     *             parameter of the same name. Register a separate {@see HookInterface} with a lower
     *             priority than this one instead — it runs after fixtures are imported the same way,
     *             without a special case here.
     */
    public const OPTION_POST_LOAD_FIXTURES = 'post_load_fixtures';

    private FixtureProviderInterface $provider;

    private FixtureImporter $fixtureImporter;

    public function __construct(
        FixtureProviderInterface $provider,
        FixtureImporter $fixtureImporter
    ) {
        $this->provider = $provider;
        $this->fixtureImporter = $fixtureImporter;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define(self::OPTION_LOAD_FIXTURES)
            ->default(true)
            ->allowedTypes('bool');

        $resolver->define(self::OPTION_POST_LOAD_FIXTURES)
            ->default(null)
            ->allowedTypes('callable', 'null')
            ->deprecated(
                'ibexa/test-core',
                '4.6.0',
                'The "%name%" option is deprecated, register a separate hook (tagged "ibexa.test.bootstrapper.hook" with a lower priority than FixtureHook\'s) instead.',
            );
    }

    public function __invoke(array $options): void
    {
        if (!$options[self::OPTION_LOAD_FIXTURES]) {
            return;
        }

        foreach ($this->provider->getFixtures() ?? [] as $fixture) {
            $this->fixtureImporter->import($fixture);
        }

        if ($options[self::OPTION_POST_LOAD_FIXTURES] !== null) {
            ($options[self::OPTION_POST_LOAD_FIXTURES])();
        }
    }
}
