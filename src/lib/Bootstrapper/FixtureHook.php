<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\HookInterface;
use Ibexa\Contracts\Test\Core\IbexaTestKernel;
use Ibexa\Contracts\Core\Test\Persistence\Fixture\FixtureImporter;

/**
 * Imports the fixtures exposed by the test kernel via {@see IbexaTestKernel::getFixtures()}.
 *
 * Enabled by default; pass `['fixtures' => false]` as bootstrap options to skip it.
 */
final class FixtureHook implements HookInterface
{
    private IbexaTestKernel $kernel;
    private FixtureImporter $fixtureImporter;

    public function __construct(
        IbexaTestKernel $kernel,
        FixtureImporter $fixtureImporter
    ) {
        $this->kernel = $kernel;
        $this->fixtureImporter = $fixtureImporter;
    }

    public function __invoke(array $options): void
    {
        if (!($options['fixtures'] ?? true)) {
            return;
        }

        foreach ($this->kernel->getFixtures() as $fixture) {
            $this->fixtureImporter->import($fixture);
        }
    }
}
