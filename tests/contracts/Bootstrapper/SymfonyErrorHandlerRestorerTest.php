<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\SymfonyErrorHandlerRestorer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorHandler;

#[CoversClass(SymfonyErrorHandlerRestorer::class)]
final class SymfonyErrorHandlerRestorerTest extends TestCase
{
    public function testPopsSymfonyErrorHandlerOffTheStack(): void
    {
        set_error_handler([new ErrorHandler(), 'handleError']);

        (new SymfonyErrorHandlerRestorer())->restoreIfSymfonyHandlerIsOnTop();

        self::assertFalse(
            self::isSymfonyErrorHandlerActive(),
            'Symfony\'s ErrorHandler should have been popped off the handler stack.',
        );
    }

    public function testLeavesAForeignHandlerAlone(): void
    {
        $before = self::peekActiveHandler();

        (new SymfonyErrorHandlerRestorer())->restoreIfSymfonyHandlerIsOnTop();

        self::assertSame(
            $before,
            self::peekActiveHandler(),
            'A non-Symfony handler (e.g. PHPUnit\'s own) must not be touched.',
        );
    }

    private static function isSymfonyErrorHandlerActive(): bool
    {
        $handler = self::peekActiveHandler();

        return is_array($handler) && $handler[0] instanceof ErrorHandler;
    }

    /**
     * Reads the currently active error handler without altering the stack.
     */
    private static function peekActiveHandler(): ?callable
    {
        $handler = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        return $handler;
    }
}
