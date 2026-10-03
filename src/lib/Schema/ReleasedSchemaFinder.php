<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema;

use Ibexa\Contracts\Test\Core\Schema\ReleasedSchema;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Finds every distinct version a package's schema file had across its release tags.
 *
 * @internal
 */
final class ReleasedSchemaFinder
{
    private const RELEASE_TAG = '/^v\d+\.\d+\.\d+$/';

    private ?GitRepository $git;

    private string $packageRoot;

    public function __construct(?GitRepository $git, string $packageRoot)
    {
        $this->git = $git;
        $this->packageRoot = rtrim($packageRoot, '/');
    }

    /**
     * @param array<string, string> $history tag pattern (e.g. "v4.6.*") => schema file path relative
     *        to the package root at those tags, oldest series first; the last path is the current file
     */
    public function find(array $history): ReleasedSchemaHistory
    {
        if ($history === []) {
            return new ReleasedSchemaHistory([], []);
        }

        $currentPath = $this->packageRoot . '/' . (string)end($history);
        $current = is_file($currentPath) ? self::parse((string)file_get_contents($currentPath)) : null;
        if ($current === null) {
            return new ReleasedSchemaHistory([], [], [sprintf('The current schema file "%s" doesn\'t exist or isn\'t valid YAML.', $currentPath)]);
        }

        if ($this->git === null) {
            return new ReleasedSchemaHistory([], [], [sprintf('"%s" isn\'t a git work tree, so released schemas can\'t be read from tags.', $this->packageRoot)], true);
        }

        $prefix = $this->git->getPrefix();
        /** @var array<string, array{yaml: string, tags: list<string>}> $versions blob id => version */
        $versions = [];
        $problems = [];
        $tagCount = 0;
        foreach ($history as $pattern => $path) {
            $tags = array_values(array_filter(
                $this->git->listTags($pattern),
                static fn (string $tag): bool => preg_match(self::RELEASE_TAG, $tag) === 1
            ));
            usort($tags, static fn (string $a, string $b): int => version_compare($a, $b));
            $tagCount += count($tags);

            $found = 0;
            foreach ($tags as $tag) {
                $blobId = $this->git->getBlobId($tag, $prefix . $path);
                if ($blobId === null) {
                    continue;
                }
                ++$found;
                if (!isset($versions[$blobId])) {
                    $versions[$blobId] = ['yaml' => $this->git->readBlob($blobId), 'tags' => []];
                }
                $versions[$blobId]['tags'][] = $tag;
            }

            if ($tags !== [] && $found === 0) {
                $problems[] = sprintf('"%s" doesn\'t exist at any %s tag.', $path, $pattern);
            }
        }

        if ($tagCount === 0) {
            return new ReleasedSchemaHistory([], [], [sprintf(
                'No release tags matching %s were found in "%s". Fetch them with: git fetch --no-tags --depth=1 origin %s',
                implode(', ', array_keys($history)),
                $this->packageRoot,
                implode(' ', array_map(
                    static fn (string $pattern): string => sprintf("'+refs/tags/%s:refs/tags/%s'", $pattern, $pattern),
                    array_keys($history)
                ))
            )], true);
        }

        // Versions that only differ in formatting or comments are the same schema.
        /** @var array<string, array{yaml: string, tags: list<string>}> $schemas */
        $schemas = [];
        $currentTags = [];
        foreach ($versions as $version) {
            $parsed = self::parse($version['yaml']);
            if ($parsed === null) {
                $problems[] = sprintf('The schema file at %s isn\'t valid YAML.', implode(', ', $version['tags']));
                continue;
            }

            if ($parsed === $current) {
                $currentTags = array_merge($currentTags, $version['tags']);
                continue;
            }

            $key = serialize($parsed);
            if (!isset($schemas[$key])) {
                $schemas[$key] = ['yaml' => $version['yaml'], 'tags' => []];
            }
            $schemas[$key]['tags'] = array_merge($schemas[$key]['tags'], $version['tags']);
        }

        $releasedSchemas = [];
        foreach ($schemas as $schema) {
            $tags = $schema['tags'];
            usort($tags, static fn (string $a, string $b): int => version_compare($a, $b));
            $releasedSchemas[] = new ReleasedSchema($this->describe($tags, $history), $schema['yaml'], $tags);
        }
        usort(
            $releasedSchemas,
            static fn (ReleasedSchema $a, ReleasedSchema $b): int => version_compare($a->getTags()[0], $b->getTags()[0])
        );
        usort($currentTags, static fn (string $a, string $b): int => version_compare($a, $b));

        return new ReleasedSchemaHistory($releasedSchemas, $currentTags, $problems);
    }

    /**
     * "v4.6.0..v4.6.20", or "v4.6.0..v4.6.3+v4.6.7" when other versions came in between.
     *
     * @param list<string> $tags sorted
     * @param array<string, string> $history
     */
    private function describe(array $tags, array $history): string
    {
        $all = [];
        foreach (array_keys($history) as $pattern) {
            foreach ($this->git !== null ? $this->git->listTags($pattern) : [] as $tag) {
                if (preg_match(self::RELEASE_TAG, $tag) === 1) {
                    $all[] = $tag;
                }
            }
        }
        usort($all, static fn (string $a, string $b): int => version_compare($a, $b));
        $position = array_flip($all);

        $ranges = [];
        $start = $end = null;
        foreach ($tags as $tag) {
            if ($end !== null && ($position[$tag] ?? -1) === ($position[$end] ?? -2) + 1) {
                $end = $tag;
                continue;
            }
            if ($start !== null) {
                $ranges[] = $start === $end ? $start : $start . '..' . $end;
            }
            $start = $end = $tag;
        }
        if ($start !== null) {
            $ranges[] = $start === $end ? $start : $start . '..' . $end;
        }

        return implode('+', $ranges);
    }

    /**
     * @return array<mixed>|null
     */
    private static function parse(string $yaml): ?array
    {
        try {
            $parsed = Yaml::parse($yaml);
        } catch (ParseException $e) {
            return null;
        }

        return is_array($parsed) ? $parsed : null;
    }
}
