<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Bootstrapper;

use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * @internal
 */
final class ConsoleCommandRunner
{
    /**
     * @param array<string, mixed> $parameters
     *
     * @throws \Exception
     */
    public function run(
        Application $application,
        array $parameters
    ): void {
        $exitCode = $application->run(new ArrayInput($parameters));
        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf(
                'Command "%s" failed with exit code %d.',
                $parameters['command'],
                $exitCode,
            ));
        }
    }
}
