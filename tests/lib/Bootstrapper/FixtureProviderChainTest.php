<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Bootstrapper;

use Ibexa\Contracts\Core\Test\Persistence\Fixture;
use Ibexa\Contracts\Test\Core\Bootstrapper\FixtureProviderInterface;
use Ibexa\Test\Core\Bootstrapper\FixtureProviderChain;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Test\Core\Bootstrapper\FixtureProviderChain
 */
final class FixtureProviderChainTest extends TestCase
{
    public function testSkipsProvidersReturningNull(): void
    {
        $fixture = $this->createFixture();

        $chain = new FixtureProviderChain([
            $this->providerReturning(null),
            $this->providerReturning([$fixture]),
        ]);

        self::assertSame([$fixture], $chain->getFixtures());
    }

    public function testStopsAtFirstNonNullResultEvenIfEmpty(): void
    {
        $chain = new FixtureProviderChain([
            $this->providerReturning([]),
            $this->providerReturning([$this->createFixture()]),
        ]);

        self::assertSame([], $chain->getFixtures());
    }

    public function testTriesProvidersInTheGivenOrder(): void
    {
        $first = $this->createFixture();
        $second = $this->createFixture();

        $chain = new FixtureProviderChain([
            $this->providerReturning([$first]),
            $this->providerReturning([$second]),
        ]);

        self::assertSame([$first], $chain->getFixtures());
    }

    public function testReturnsNullWhenNoProviderHasAnAnswer(): void
    {
        $chain = new FixtureProviderChain([
            $this->providerReturning(null),
            $this->providerReturning(null),
        ]);

        self::assertNull($chain->getFixtures());
    }

    private function createFixture(): Fixture
    {
        return new class() implements Fixture {
            /**
             * @return array<string, mixed>
             */
            public function load(): array
            {
                return [];
            }
        };
    }

    /**
     * @param iterable<Fixture>|null $fixtures
     */
    private function providerReturning(?iterable $fixtures): FixtureProviderInterface
    {
        return new class($fixtures) implements FixtureProviderInterface {
            /**
             * @var iterable<Fixture>|null
             */
            private ?iterable $fixtures;

            /**
             * @param iterable<Fixture>|null $fixtures
             */
            public function __construct(?iterable $fixtures)
            {
                $this->fixtures = $fixtures;
            }

            public function getFixtures(): ?iterable
            {
                return $this->fixtures;
            }
        };
    }
}
