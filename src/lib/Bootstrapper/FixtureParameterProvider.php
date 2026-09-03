<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\Persistence\Fixture\YamlFixture;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureProviderInterface;

/**
 * Reads a list of YAML fixture file paths off a container parameter, injected directly (not looked
 * up from a parameter bag at runtime), wrapping each path in a {@see YamlFixture}. A parameter can
 * only hold scalars/arrays, not live Fixture objects, so this only covers the YAML-based fixture
 * shape — a kernel needing a different Fixture implementation still needs its own provider.
 */
final class FixtureParameterProvider implements FixtureProviderInterface
{
    /**
     * @var iterable<string>|null
     */
    private ?iterable $fixtureFiles;

    /**
     * @param iterable<string>|null $fixtureFiles
     */
    public function __construct(?iterable $fixtureFiles)
    {
        $this->fixtureFiles = $fixtureFiles;
    }

    public function getFixtures(): ?iterable
    {
        if ($this->fixtureFiles === null) {
            return null;
        }

        return $this->wrapAsFixtures($this->fixtureFiles);
    }

    /**
     * @param iterable<string> $fixtureFiles
     *
     * @return iterable<YamlFixture>
     */
    private function wrapAsFixtures(iterable $fixtureFiles): iterable
    {
        foreach ($fixtureFiles as $fixtureFile) {
            yield new YamlFixture($fixtureFile);
        }
    }
}
