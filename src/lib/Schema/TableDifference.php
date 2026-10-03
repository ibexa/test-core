<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema;

/**
 * How one table differs between the schema path ("expected") and the migrations path ("actual").
 *
 * @internal
 */
final class TableDifference
{
    private string $table;

    private bool $tableMissing;

    private bool $tableUnexpected;

    /** @var list<string> */
    private array $missingLines;

    /** @var list<string> */
    private array $unexpectedLines;

    /**
     * @param list<string> $missingLines
     * @param list<string> $unexpectedLines
     */
    public function __construct(
        string $table,
        array $missingLines,
        array $unexpectedLines,
        bool $tableMissing = false,
        bool $tableUnexpected = false
    ) {
        $this->table = $table;
        $this->missingLines = $missingLines;
        $this->unexpectedLines = $unexpectedLines;
        $this->tableMissing = $tableMissing;
        $this->tableUnexpected = $tableUnexpected;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * The schema path has the table, the migrations path doesn't.
     */
    public function isTableMissing(): bool
    {
        return $this->tableMissing;
    }

    /**
     * The migrations path has a table the schema path doesn't (e.g. one a release dropped).
     */
    public function isTableUnexpected(): bool
    {
        return $this->tableUnexpected;
    }

    /**
     * @return list<string>
     */
    public function getMissingLines(): array
    {
        return $this->missingLines;
    }

    /**
     * @return list<string>
     */
    public function getUnexpectedLines(): array
    {
        return $this->unexpectedLines;
    }

    public function isEmpty(): bool
    {
        return !$this->tableMissing
            && !$this->tableUnexpected
            && $this->missingLines === []
            && $this->unexpectedLines === [];
    }
}
