<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\SchemaFilesProviderInterface;
use Ibexa\Test\Core\Bootstrapper\SchemaFilesProviderChain;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Test\Core\Bootstrapper\SchemaFilesProviderChain
 */
final class SchemaFilesProviderChainTest extends TestCase
{
    public function testSkipsProvidersReturningNull(): void
    {
        $chain = new SchemaFilesProviderChain([
            $this->providerReturning(null),
            $this->providerReturning(['a.sql']),
        ]);

        self::assertSame(['a.sql'], $chain->getSchemaFiles());
    }

    public function testStopsAtFirstNonNullResultEvenIfEmpty(): void
    {
        $chain = new SchemaFilesProviderChain([
            $this->providerReturning([]),
            $this->providerReturning(['never-reached.sql']),
        ]);

        self::assertSame([], $chain->getSchemaFiles());
    }

    public function testTriesProvidersInTheGivenOrder(): void
    {
        $chain = new SchemaFilesProviderChain([
            $this->providerReturning(['first.sql']),
            $this->providerReturning(['second.sql']),
        ]);

        self::assertSame(['first.sql'], $chain->getSchemaFiles());
    }

    public function testReturnsNullWhenNoProviderHasAnAnswer(): void
    {
        $chain = new SchemaFilesProviderChain([
            $this->providerReturning(null),
            $this->providerReturning(null),
        ]);

        self::assertNull($chain->getSchemaFiles());
    }

    /**
     * @param iterable<string>|null $schemaFiles
     */
    private function providerReturning(?iterable $schemaFiles): SchemaFilesProviderInterface
    {
        return new class($schemaFiles) implements SchemaFilesProviderInterface {
            /**
             * @var iterable<string>|null
             */
            private ?iterable $schemaFiles;

            /**
             * @param iterable<string>|null $schemaFiles
             */
            public function __construct(?iterable $schemaFiles)
            {
                $this->schemaFiles = $schemaFiles;
            }

            public function getSchemaFiles(): ?iterable
            {
                return $this->schemaFiles;
            }
        };
    }
}
