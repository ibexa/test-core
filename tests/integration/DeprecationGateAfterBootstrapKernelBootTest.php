<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Test\Core;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * A kernel booted from a PHPUnit `<bootstrap>` file boots before PHPUnit installs its error handler. If
 * FrameworkBundle::boot() registers Symfony's ErrorHandler on that empty stack, PHPUnit's handler never
 * activates and failOnDeprecation silently catches nothing. With symfony/runtime installed,
 * FrameworkBundle::boot() leaves the stack alone.
 */
#[Group('integration')]
#[CoversNothing]
final class DeprecationGateAfterBootstrapKernelBootTest extends TestCase
{
    public function testKernelBootLeavesAnEmptyErrorHandlerStackEmpty(): void
    {
        $exceptionHandler = set_exception_handler(null);
        restore_exception_handler();

        // PHPUnit's handler is active inside a test. Hide it so boot() sees the empty stack a <bootstrap> file sees.
        set_error_handler(null);

        try {
            $kernel = new TestKernel('test', true);
            $kernel->boot();
            $installed = get_error_handler();
            $kernel->shutdown();
        } finally {
            // Pop whatever boot() pushed on top of the null handler, then the null handler itself.
            for ($i = 0; $i < 8 && get_error_handler() !== null; ++$i) {
                restore_error_handler();
            }
            restore_error_handler();

            // ErrorHandler::register() pushes an exception handler as well.
            for ($i = 0; $i < 8; ++$i) {
                $top = set_exception_handler(null);
                restore_exception_handler();
                if ($top === $exceptionHandler) {
                    break;
                }
                restore_exception_handler();
            }
        }

        self::assertNull(
            $this->describe($installed),
            'FrameworkBundle::boot() installed an error handler on an empty stack. Booted from a PHPUnit <bootstrap> '
            . 'file, that handler would stay in place, PHPUnit\'s own handler would never activate and '
            . 'failOnDeprecation would catch nothing. Is symfony/runtime installed?',
        );
    }

    private function describe(?callable $handler): ?string
    {
        if ($handler === null) {
            return null;
        }

        if (is_array($handler) && is_object($handler[0])) {
            return $handler[0]::class . '::' . $handler[1];
        }

        return get_debug_type($handler);
    }
}
