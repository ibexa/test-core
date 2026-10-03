<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Schema;

use Ibexa\Contracts\Test\Core\Schema\ReleasedSchema;

/**
 * Where a schema parity scenario starts before the migrations run.
 *
 * @internal
 */
final class Scenario
{
    public const FRESH_INSTALL = 'fresh-install';
    public const CURRENT_LEGACY_INSTALL = 'current-legacy-install';
    public const PACKAGE_ADDED = 'package-added-to-legacy-install';
    public const UPGRADE = 'upgrade';

    private string $kind;

    private string $id;

    private string $databaseSlug;

    private string $startingPoint;

    private string $likelyCause;

    private ?ReleasedSchema $releasedSchema;

    private function __construct(
        string $kind,
        string $id,
        string $databaseSlug,
        string $startingPoint,
        string $likelyCause,
        ?ReleasedSchema $releasedSchema = null
    ) {
        $this->kind = $kind;
        $this->id = $id;
        $this->databaseSlug = $databaseSlug;
        $this->startingPoint = $startingPoint;
        $this->likelyCause = $likelyCause;
        $this->releasedSchema = $releasedSchema;
    }

    public static function freshInstall(): self
    {
        return new self(
            self::FRESH_INSTALL,
            self::FRESH_INSTALL,
            'fresh',
            'an empty database',
            'the migrations\' baseline SQL differs from the schema definition (was the schema changed without regenerating the SQL?).'
        );
    }

    /**
     * @param list<string> $tags release tags whose schema equals the current one
     */
    public static function currentLegacyInstall(array $tags = []): self
    {
        $startingPoint = 'a legacy (schema path) install of the current version';
        if ($tags !== []) {
            $startingPoint .= sprintf(', which is also how %s left it', self::describeTags($tags));
        }

        return new self(
            self::CURRENT_LEGACY_INSTALL,
            self::CURRENT_LEGACY_INSTALL,
            'legacy',
            $startingPoint,
            'a migration changes a database that is already up to date: its guard (hasTable/hasColumn/hasIndex) doesn\'t detect that it has nothing to do.'
        );
    }

    public static function packageAdded(): self
    {
        return new self(
            self::PACKAGE_ADDED,
            self::PACKAGE_ADDED,
            'added',
            'a legacy install of every other bundle, without this package\'s tables',
            'the baseline migration\'s guard skips a database that doesn\'t have this package\'s tables, or the baseline doesn\'t create all of them.'
        );
    }

    public static function upgradeFrom(ReleasedSchema $releasedSchema): self
    {
        $label = $releasedSchema->getLabel();
        $parts = preg_split('/\.\.|\+/', $label);
        $last = is_array($parts) && $parts !== [] ? (string)end($parts) : $label;

        return new self(
            self::UPGRADE,
            'upgrade-from-' . $label,
            'upgrade_' . $last,
            sprintf('a legacy install where this package\'s tables are as they were at %s', $label),
            'a schema change made since then has no migration, or its migration doesn\'t produce what the schema definition declares. Add or fix a migration guarded with hasTable/hasColumn/hasIndex.',
            $releasedSchema
        );
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDatabaseSlug(): string
    {
        return $this->databaseSlug;
    }

    public function getStartingPoint(): string
    {
        return $this->startingPoint;
    }

    public function getLikelyCause(): string
    {
        return $this->likelyCause;
    }

    public function getReleasedSchema(): ?ReleasedSchema
    {
        return $this->releasedSchema;
    }

    public function startsFromLegacyInstall(): bool
    {
        return $this->kind !== self::FRESH_INSTALL;
    }

    public function __toString(): string
    {
        return $this->id;
    }

    /**
     * @param list<string> $tags
     */
    private static function describeTags(array $tags): string
    {
        if (count($tags) === 1) {
            return $tags[0];
        }

        return sprintf('%s..%s', $tags[0], $tags[count($tags) - 1]);
    }
}
