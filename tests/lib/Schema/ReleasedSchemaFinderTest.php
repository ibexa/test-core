<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Schema;

use Ibexa\Test\Core\Schema\GitRepository;
use Ibexa\Test\Core\Schema\ReleasedSchemaFinder;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Test\Core\Schema\GitRepository
 * @covers \Ibexa\Test\Core\Schema\ReleasedSchemaFinder
 * @covers \Ibexa\Test\Core\Schema\ReleasedSchemaHistory
 */
final class ReleasedSchemaFinderTest extends TestCase
{
    private const SCHEMA_FILE = 'src/bundle/Resources/config/schema.yaml';

    private const V1 = "tables:\n    t:\n        id:\n            id: { type: integer, nullable: false }\n";
    private const V2 = "tables:\n    t:\n        indexes:\n            t_a: { fields: [a] }\n        id:\n            id: { type: integer, nullable: false }\n        fields:\n            a: { type: integer, nullable: true }\n";

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/ibexa-released-schema-finder-' . bin2hex(random_bytes(4));
        mkdir($this->directory . '/' . dirname(self::SCHEMA_FILE), 0777, true);
        if (!$this->git('init', '--quiet')) {
            self::markTestSkipped('git isn\'t available.');
        }
        $this->git('config', 'user.email', 'test@example.com');
        $this->git('config', 'user.name', 'Test');
        $this->git('config', 'commit.gpgsign', 'false');
        $this->git('config', 'tag.gpgsign', 'false');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->directory));
    }

    public function testFindsEveryDistinctReleasedSchema(): void
    {
        $this->release('v4.6.0', self::V1);
        $this->release('v4.6.1', "# a comment only\n" . self::V1);
        $this->release('v4.6.2-rc1', self::V2);
        $this->release('v4.6.2', self::V2);
        $this->release('v4.6.10', self::V2);
        $this->commit("tables:\n    t:\n        id:\n            id: { type: bigint, nullable: false }\n");

        $history = $this->find();

        self::assertSame([], $history->getProblems());
        self::assertCount(2, $history->getReleasedSchemas());
        self::assertSame('v4.6.0..v4.6.1', $history->getReleasedSchemas()[0]->getLabel());
        self::assertSame(['v4.6.0', 'v4.6.1'], $history->getReleasedSchemas()[0]->getTags());
        self::assertSame('v4.6.2..v4.6.10', $history->getReleasedSchemas()[1]->getLabel());
        self::assertSame(['t'], $history->getReleasedSchemas()[1]->getTableNames());
        self::assertSame([], $history->getCurrentTags());
    }

    public function testReleasesWithTheCurrentSchemaAreNotUpgradeSources(): void
    {
        $this->release('v4.6.0', self::V1);
        $this->release('v4.6.1', self::V2);
        $this->release('v4.6.2', self::V2);

        $history = $this->find();

        self::assertCount(1, $history->getReleasedSchemas());
        self::assertSame('v4.6.0', $history->getReleasedSchemas()[0]->getLabel());
        self::assertSame(['v4.6.1', 'v4.6.2'], $history->getCurrentTags());
    }

    public function testInterleavedVersionsGetOneLabel(): void
    {
        $this->release('v4.6.0', self::V1);
        $this->release('v4.6.1', self::V2);
        $this->release('v4.6.2', self::V1);
        $this->commit(self::V2 . "    u:\n        id:\n            id: { type: integer, nullable: false }\n");

        $history = $this->find();

        self::assertCount(2, $history->getReleasedSchemas());
        self::assertSame('v4.6.0+v4.6.2', $history->getReleasedSchemas()[0]->getLabel());
        self::assertSame('v4.6.1', $history->getReleasedSchemas()[1]->getLabel());
    }

    public function testMissingTagsAreReported(): void
    {
        $this->commit(self::V1);

        $history = $this->find();

        self::assertTrue($history->areTagsMissing());
        self::assertSame([], $history->getReleasedSchemas());
        self::assertStringContainsString("git fetch --no-tags --depth=1 origin '+refs/tags/v4.6.*:refs/tags/v4.6.*'", $history->getProblems()[0]);
    }

    private function find(): \Ibexa\Test\Core\Schema\ReleasedSchemaHistory
    {
        return (new ReleasedSchemaFinder(GitRepository::locate($this->directory), $this->directory))
            ->find(['v4.6.*' => self::SCHEMA_FILE]);
    }

    private function release(string $tag, string $schema): void
    {
        $this->commit($schema);
        $this->git('tag', $tag);
    }

    private function commit(string $schema): void
    {
        file_put_contents($this->directory . '/' . self::SCHEMA_FILE, $schema);
        $this->git('add', '-A');
        $this->git('commit', '--quiet', '--allow-empty', '-m', 'change');
    }

    private function git(string ...$arguments): bool
    {
        exec(
            sprintf('git -C %s %s 2>&1', escapeshellarg($this->directory), implode(' ', array_map('escapeshellarg', $arguments))),
            $output,
            $exitCode
        );

        return $exitCode === 0;
    }
}
