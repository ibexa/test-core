<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Contracts\Test\Core\Bootstrapper;

use Ibexa\Contracts\Test\Core\Bootstrapper\ConsoleCommandRunner;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;

/**
 * @covers \Ibexa\Contracts\Test\Core\Bootstrapper\ConsoleCommandRunner
 */
final class ConsoleCommandRunnerTest extends TestCase
{
    public function testDoesNothingOnSuccess(): void
    {
        $application = $this->createMock(Application::class);
        $application->method('run')->willReturn(0);

        (new ConsoleCommandRunner())->run($application, ['command' => 'doctrine:database:create']);

        $this->expectNotToPerformAssertions();
    }

    public function testThrowsNamingTheCommandAndExitCodeOnFailure(): void
    {
        $application = $this->createMock(Application::class);
        $application->method('run')->willReturn(2);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Command "doctrine:database:create" failed with exit code 2.');

        (new ConsoleCommandRunner())->run($application, ['command' => 'doctrine:database:create']);
    }
}
