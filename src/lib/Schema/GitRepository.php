<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema;

use RuntimeException;

/**
 * The few read-only git commands the schema parity test needs to find released schema files.
 *
 * @internal
 */
final class GitRepository
{
    private string $directory;

    private function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    /**
     * Returns null when the directory isn't inside a git work tree, or git isn't available.
     */
    public static function locate(string $directory): ?self
    {
        $repository = new self($directory);
        $output = $repository->run(['rev-parse', '--is-inside-work-tree'], true);

        return $output !== null && trim($output) === 'true' ? $repository : null;
    }

    /**
     * The directory's path relative to the repository root, e.g. "" or "packages/foo/".
     */
    public function getPrefix(): string
    {
        return trim((string)$this->run(['rev-parse', '--show-prefix']));
    }

    /**
     * @return list<string>
     */
    public function listTags(string $pattern): array
    {
        $output = trim((string)$this->run(['tag', '--list', $pattern]));

        return $output === '' ? [] : array_values(array_filter(array_map('trim', explode("\n", $output))));
    }

    /**
     * Returns null when the path doesn't exist at that revision.
     */
    public function getBlobId(string $revision, string $path): ?string
    {
        $output = $this->run(['rev-parse', '--verify', '--quiet', $revision . ':' . $path], true);
        if ($output === null) {
            return null;
        }

        $blobId = trim($output);

        return $blobId === '' ? null : $blobId;
    }

    public function readBlob(string $blobId): string
    {
        return (string)$this->run(['cat-file', 'blob', $blobId]);
    }

    /**
     * @param list<string> $arguments
     *
     * @return string|null null when the command failed and $allowFailure is set
     */
    private function run(array $arguments, bool $allowFailure = false): ?string
    {
        $process = @proc_open(
            array_merge(['git', '-C', $this->directory], $arguments),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            if ($allowFailure) {
                return null;
            }

            throw new RuntimeException('Unable to run git.');
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            if ($allowFailure) {
                return null;
            }

            throw new RuntimeException(sprintf(
                'git %s failed in "%s": %s',
                implode(' ', $arguments),
                $this->directory,
                trim((string)$error)
            ));
        }

        return (string)$output;
    }
}
