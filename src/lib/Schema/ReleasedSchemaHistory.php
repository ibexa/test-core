<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema;

use Ibexa\Contracts\Test\Core\Schema\ReleasedSchema;

/**
 * What {@see ReleasedSchemaFinder} found: the released schemas that differ from the current one,
 * the release tags whose schema equals the current one, and anything that kept it from looking.
 *
 * @internal
 */
final class ReleasedSchemaHistory
{
    /** @var list<ReleasedSchema> */
    private array $releasedSchemas;

    /** @var list<string> */
    private array $currentTags;

    /** @var list<string> */
    private array $problems;

    /** Problems that tags would fix (as opposed to a misconfigured history). */
    private bool $tagsMissing;

    /**
     * @param list<ReleasedSchema> $releasedSchemas
     * @param list<string> $currentTags
     * @param list<string> $problems
     */
    public function __construct(array $releasedSchemas, array $currentTags, array $problems = [], bool $tagsMissing = false)
    {
        $this->releasedSchemas = $releasedSchemas;
        $this->currentTags = $currentTags;
        $this->problems = $problems;
        $this->tagsMissing = $tagsMissing;
    }

    /**
     * @return list<ReleasedSchema>
     */
    public function getReleasedSchemas(): array
    {
        return $this->releasedSchemas;
    }

    /**
     * @return list<string>
     */
    public function getCurrentTags(): array
    {
        return $this->currentTags;
    }

    /**
     * @return list<string>
     */
    public function getProblems(): array
    {
        return $this->problems;
    }

    public function areTagsMissing(): bool
    {
        return $this->tagsMissing;
    }
}
