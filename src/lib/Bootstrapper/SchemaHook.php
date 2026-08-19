<?php

namespace Ibexa\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\HookInterface;
use Ibexa\Tests\Core\Repository\LegacySchemaImporter;
use Symfony\Component\HttpKernel\KernelInterface;

final class SchemaHook implements HookInterface
{
    private KernelInterface $kernel;
    private LegacySchemaImporter $schemaImporter;

    public function __construct(
        KernelInterface $kernel,
        LegacySchemaImporter $schemaImporter
    ) {
        $this->kernel = $kernel;
        $this->schemaImporter = $schemaImporter;
    }

    public function __invoke(): void
    {
        foreach ($this->kernel->getSchemaFiles() as $file) {
            $this->schemaImporter->importSchema($file);
        }
    }
}
