<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Schema;

use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * A package's tables as some released versions had them, in the doctrine-schema YAML format
 * (the format of a package's schema.yaml).
 *
 * {@see AbstractSchemaParityTestCase} discovers these from git tags for packages whose tables come
 * from a schema.yaml file. Packages whose tables come from somewhere else (ORM entities, PHP code)
 * provide them as committed snapshot files instead, see {@see ReleasedSchema::fromFile()}.
 *
 * @experimental
 */
final class ReleasedSchema
{
    private string $label;

    private string $yaml;

    /** @var list<string> */
    private array $tags;

    /**
     * @param string $label the releases this schema covers, e.g. "v4.6.0..v4.6.28"
     * @param list<string> $tags
     */
    public function __construct(string $label, string $yaml, array $tags = [])
    {
        $this->label = $label;
        $this->yaml = $yaml;
        $this->tags = $tags;
    }

    public static function fromFile(string $label, string $path): self
    {
        $yaml = is_file($path) ? file_get_contents($path) : false;
        if ($yaml === false) {
            throw new RuntimeException(sprintf('Unable to read the released schema "%s" from "%s".', $label, $path));
        }

        return new self($label, $yaml);
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getYaml(): string
    {
        return $this->yaml;
    }

    /**
     * @return list<string> the release tags this schema was found at, if it was discovered from tags
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @return list<string>
     */
    public function getTableNames(): array
    {
        $definition = Yaml::parse($this->yaml);
        if (!is_array($definition) || !isset($definition['tables']) || !is_array($definition['tables'])) {
            throw new RuntimeException(sprintf('The released schema "%s" has no "tables" key.', $this->label));
        }

        return array_map('strval', array_keys($definition['tables']));
    }
}
