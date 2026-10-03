<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Schema;

/**
 * A known difference between the schema path and the migrations path that a package accepts, so
 * {@see AbstractSchemaParityTestCase} doesn't report it.
 *
 * It names a table and a line of that table's normalized DDL, as the failure report prints it.
 * The line may contain "*" wildcards. Keep the list short, and give every entry a reason.
 *
 * @experimental
 */
final class ExpectedDifference
{
    public const MISSING = 'missing';
    public const UNEXPECTED = 'unexpected';

    private string $table;

    private string $kind;

    private string $line;

    /** @var list<string> */
    private array $platforms = [];

    /** @var list<string> */
    private array $scenarios = [];

    private string $reason = '';

    private function __construct(string $table, string $kind, string $line)
    {
        $this->table = $table;
        $this->kind = $kind;
        $this->line = $line;
    }

    /**
     * A line the schema path has, but the migrations path doesn't.
     */
    public static function missing(string $table, string $line): self
    {
        return new self($table, self::MISSING, $line);
    }

    /**
     * A line the migrations path has, but the schema path doesn't.
     */
    public static function unexpected(string $table, string $line): self
    {
        return new self($table, self::UNEXPECTED, $line);
    }

    /**
     * Limits this to some platforms: "mysql", "mariadb", "postgresql" or "sqlite".
     */
    public function onPlatforms(string ...$platforms): self
    {
        $copy = clone $this;
        $copy->platforms = array_values($platforms);

        return $copy;
    }

    /**
     * Limits this to some scenarios, by id. Ids may contain "*" wildcards, e.g. "upgrade-from-*".
     */
    public function inScenarios(string ...$scenarios): self
    {
        $copy = clone $this;
        $copy->scenarios = array_values($scenarios);

        return $copy;
    }

    public function because(string $reason): self
    {
        $copy = clone $this;
        $copy->reason = $reason;

        return $copy;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function matches(string $table, string $kind, string $line, string $platform, string $scenario): bool
    {
        if ($table !== $this->table || $kind !== $this->kind || !self::wildcardMatch($this->line, $line)) {
            return false;
        }

        if ($this->platforms !== [] && !in_array($platform, $this->platforms, true)) {
            return false;
        }

        if ($this->scenarios === []) {
            return true;
        }

        foreach ($this->scenarios as $pattern) {
            if (self::wildcardMatch($pattern, $scenario)) {
                return true;
            }
        }

        return false;
    }

    private static function wildcardMatch(string $pattern, string $subject): bool
    {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/s';

        return preg_match($regex, $subject) === 1;
    }
}
