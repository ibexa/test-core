<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\IbexaTestKernelInterface;
use Ibexa\Contracts\Core\Test\Persistence\Fixture\FixtureImporter;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Imports the fixtures exposed by the test kernel via {@see IbexaTestKernelInterface::getFixtures()}.
 *
 * Typed against the interface, not the concrete Ibexa\Contracts\Test\Core\IbexaTestKernel: Symfony's
 * autowiring only resolves the synthetic "kernel" service by the interfaces it implements, not by
 * intermediate parent classes.
 *
 * Enabled by default; pass `['load_fixtures' => false]` as this hook's own options (keyed by its own
 * service id in the bootstrap options array) to skip it.
 */
final class FixtureHook implements HookInterface
{
    private IbexaTestKernelInterface $kernel;

    private FixtureImporter $fixtureImporter;

    public function __construct(
        IbexaTestKernelInterface $kernel,
        FixtureImporter $fixtureImporter
    ) {
        $this->kernel = $kernel;
        $this->fixtureImporter = $fixtureImporter;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('load_fixtures')
            ->default(true)
            ->allowedTypes('bool');
    }

    public function __invoke(array $options): void
    {
        if (!$options['load_fixtures']) {
            return;
        }

        foreach ($this->kernel->getFixtures() as $fixture) {
            $this->fixtureImporter->import($fixture);
        }
    }
}
