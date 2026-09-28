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
 * Imports the fixtures a package contributes, as exposed by {@see FixtureProviderInterface}.
 *
 * The baseline repository content these are layered on top of is not part of the chain - it is
 * imported separately by {@see BaseFixtureHook}, which runs first. A fixture here that writes into
 * tables the baseline already populates must implement
 * {@see \Ibexa\Contracts\Core\Test\Persistence\AppendOnlyFixture}, or importing it will truncate
 * those tables and take the baseline's rows with it.
 *
 * Enabled by default; pass `[self::OPTION_LOAD_FIXTURES => false]` as this hook's own options (keyed
 * by its own service id in the bootstrap options array) to skip it.
 */
final class FixtureHook implements HookInterface
{
    /**
     * Fixed tag priority this hook is registered at — runs after {@see BaseFixtureHook} (950),
     * before ibexa/migrations' MigrationHook (500), if present.
     */
    public const PRIORITY = 900;

    public const OPTION_LOAD_FIXTURES = 'load_fixtures';

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
    }

    public function __invoke(array $options): void
    {
        if (!$options[self::OPTION_LOAD_FIXTURES]) {
            return;
        }

        foreach ($this->provider->getFixtures() ?? [] as $fixture) {
            $this->fixtureImporter->import($fixture);
        }
    }
}
