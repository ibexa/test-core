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
 * Works around {@see FrameworkBundle::boot()} leaving its own {@see ErrorHandler} on top of the
 * handler stack when none was registered yet, which blocks PHPUnit's own handler from installing.
 */
final class SymfonyErrorHandlerRestorer
{
    public function restoreIfSymfonyHandlerIsOnTop(): void
    {
        $activeHandler = set_error_handler(static fn (): bool => false);
        restore_error_handler();

        if (is_array($activeHandler) && $activeHandler[0] instanceof ErrorHandler) {
            restore_error_handler();
        }
    }
}
