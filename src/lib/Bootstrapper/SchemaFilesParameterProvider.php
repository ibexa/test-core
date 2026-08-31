<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\SchemaFilesProviderInterface;

/**
 * Reads the schema file list off a container parameter, injected directly (not looked up from a
 * parameter bag at runtime) so this class has no dependency on the container itself.
 */
final class SchemaFilesParameterProvider implements SchemaFilesProviderInterface
{
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
}
