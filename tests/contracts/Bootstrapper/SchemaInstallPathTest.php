<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Bundle\RepositoryInstaller\Bootstrapper\DoctrineMigrationsSchemaHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\DatabaseSchemaHook;
use Ibexa\Contracts\Test\Core\Bootstrapper\SchemaInstallPath;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\SchemaInstallPath
 */
final class SchemaInstallPathTest extends TestCase
{
    /**
     * @dataProvider provideInstallPaths
     *
     * @param array<string, array<string, bool>> $expectedOptions
     */
    public function testEnablesExactlyOneSchemaHook(
        bool $doctrineMigrationsEnabled,
        array $expectedOptions
    ): void {
        self::assertSame($expectedOptions, SchemaInstallPath::getBootstrapperOptions($doctrineMigrationsEnabled));
    }

    /**
     * @return iterable<string, array{bool, array<string, array<string, bool>>}>
     */
    public static function provideInstallPaths(): iterable
    {
        yield 'SchemaBuilderEvent' => [
            false,
            [
                DatabaseSchemaHook::class => [DatabaseSchemaHook::OPTION_LOAD_SCHEMA => true],
                DoctrineMigrationsSchemaHook::class => [DoctrineMigrationsSchemaHook::OPTION_INSTALL_SCHEMA => false],
            ],
        ];

        yield 'Doctrine Migrations' => [
            true,
            [
                DatabaseSchemaHook::class => [DatabaseSchemaHook::OPTION_LOAD_SCHEMA => false],
                DoctrineMigrationsSchemaHook::class => [DoctrineMigrationsSchemaHook::OPTION_INSTALL_SCHEMA => true],
            ],
        ];
    }
}
