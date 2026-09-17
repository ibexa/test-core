<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\ErrorHandler\ErrorHandler;

/**
 * @internal
 *
 * On every kernel boot, {@see FrameworkBundle::boot()} calls
 * `\Symfony\Component\ErrorHandler\ErrorHandler::register(null, false)`. With `$replace = false`,
 * that call is a no-op on the error handler stack whenever a foreign (non-Symfony) handler is
 * already active: it pushes a Symfony {@see ErrorHandler} and immediately pops it again inside the
 * same call. But when *no* handler is registered yet, `register()` takes a different branch: it
 * installs the Symfony {@see ErrorHandler} unconditionally, marks it "root", and never pops it
 * back off.
 *
 * That second case is exactly what happens the first time test-core boots a kernel in a fresh
 * PHPUnit >= 10 process - e.g. from a package's `tests/integration/bootstrap.php`, which PHPUnit
 * runs (as its `<bootstrap>` file) before it installs its own handler. Left unattended, the
 * Symfony {@see ErrorHandler} stays on top, and `PHPUnit\Runner\ErrorHandler::enable()` - which
 * does `set_error_handler($this)` and, upon getting back a non-null previous handler, immediately
 * calls `restore_error_handler()` and returns without ever marking itself enabled - never actually
 * activates. `failOnDeprecation` (and anything else that depends on PHPUnit's own error handler)
 * then silently does nothing for the rest of the run.
 *
 * The fix: after every kernel boot test-core performs, remove the Symfony handler that boot just
 * pushed - but only if it is in fact still on top. If a foreign handler (PHPUnit's, or a user's)
 * is active instead - the ordinary case for any boot happening after PHPUnit has installed its own
 * handler - `register()` has already made itself a no-op, and there is nothing to undo.
 *
 * `set_exception_handler()` is deliberately left alone: Symfony's `ErrorHandler::register()` also
 * registers itself as the exception handler, but PHPUnit has no equivalent "refuse to stack"
 * behaviour for exception handlers - it wraps test execution in try/catch rather than relying on a
 * global exception handler - so there is nothing for a foreign exception handler to block.
 */
final class SymfonyErrorHandlerRestorer
{
    private function __construct() {}

    /**
     * Call immediately after a kernel boot. Pops the Symfony {@see ErrorHandler} off the handler
     * stack if (and only if) it is the one currently active, restoring whatever was registered
     * before it (PHPUnit's handler, a user's handler, or nothing).
     */
    public static function restoreIfSymfonyHandlerIsOnTop(): void
    {
        // Peek at the active handler without altering the stack: push a throwaway handler,
        // capture what set_error_handler() reports was previously active, then pop the
        // throwaway back off.
        $activeHandler = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        if (is_array($activeHandler) && $activeHandler[0] instanceof ErrorHandler) {
            // The peek showed Symfony's ErrorHandler on top - pop it off for real.
            restore_error_handler();
        }
    }
}
