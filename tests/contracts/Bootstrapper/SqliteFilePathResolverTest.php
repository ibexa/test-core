<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\SqliteFilePathResolver;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\SqliteFilePathResolver
 */
final class SqliteFilePathResolverTest extends TestCase
{
    public function testStripsExactlyOneLeadingSlash(): void
    {
        self::assertSame('path/to/file.db', (new SqliteFilePathResolver())->resolve('sqlite://i@i/path/to/file.db'));
    }

    public function testKeepsAnAbsolutePathAbsolute(): void
    {
        self::assertSame('/abs/path/to/file.db', (new SqliteFilePathResolver())->resolve('sqlite://i@i//abs/path/to/file.db'));
    }

    public function testReturnsNullForMemory(): void
    {
        self::assertNull((new SqliteFilePathResolver())->resolve('sqlite://:memory:'));
    }

    /**
     * @dataProvider unparseableSqliteDsnProvider
     */
    public function testThrowsForDsnFormsParseUrlCannotParse(string $databaseUrl): void
    {
        $this->expectException(LogicException::class);

        (new SqliteFilePathResolver())->resolve($databaseUrl);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unparseableSqliteDsnProvider(): iterable
    {
        yield 'triple slash' => ['sqlite:///path/to/file.db'];
        yield 'quadruple slash' => ['sqlite:////abs/path/to/file.db'];
    }
}
