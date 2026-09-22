<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Test\Core;

use Ibexa\Contracts\Test\Core\Bootstrapper\KernelProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Runtime\SymfonyRuntime;

/**
 * A kernel booted from a `<bootstrap>` file runs before PHPUnit installs its error handler. Without
 * symfony/runtime, FrameworkBundle::boot() then installs Symfony's ErrorHandler in that empty slot and
 * PHPUnit's never activates, so failOnDeprecation silently catches nothing. Only a real PHPUnit
 * subprocess reproduces that ordering.
 */
#[Group('integration')]
#[CoversNothing]
final class DeprecationGateAfterBootstrapKernelBootTest extends TestCase
{
    private const string PROBE_MESSAGE = 'gate probe deprecation';

    private string $workDir;

    protected function setUp(): void
    {
        $this->workDir = sys_get_temp_dir() . '/ibexa-test-core-gate-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->workDir);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->workDir);
    }

    public function testSymfonyRuntimeIsRequired(): void
    {
        self::assertTrue(
            class_exists(SymfonyRuntime::class),
            'symfony/runtime must stay a hard requirement of ibexa/test-core: FrameworkBundle::boot() skips '
            . 'ErrorHandler::register() only when SymfonyRuntime exists, and that registration blocks PHPUnit\'s '
            . 'own error handler when a kernel is booted from a <bootstrap> file.',
        );
    }

    public function testDeprecationGateFiresAfterKernelBootInBootstrapFile(): void
    {
        $projectDir = dirname(__DIR__, 2);
        $this->writeFile('bootstrap.php', sprintf(
            "<?php\nrequire %s;\n(new %s())->getKernel(%s::class);\n",
            var_export($projectDir . '/vendor/autoload.php', true),
            KernelProvider::class,
            TestKernel::class,
        ));
        $this->writeFile('GateProbeTest.php', sprintf(
            "<?php\nfinal class GateProbeTest extends \\PHPUnit\\Framework\\TestCase\n{\n"
            . "    public function testTriggersADeprecation(): void\n    {\n"
            . "        trigger_deprecation('ibexa/test-core', '6.0', %s);\n"
            . "        self::assertTrue(true);\n    }\n}\n",
            var_export(self::PROBE_MESSAGE, true),
        ));
        $this->writeFile('phpunit.xml', sprintf(
            '<?xml version="1.0"?>
<phpunit bootstrap="%1$s/bootstrap.php" failOnDeprecation="true" displayDetailsOnTestsThatTriggerDeprecations="true" colors="false" cacheDirectory="%1$s/.phpunit.cache">
    <testsuites>
        <testsuite name="probe">
            <file>%1$s/GateProbeTest.php</file>
        </testsuite>
    </testsuites>
    <source ignoreIndirectDeprecations="false" ignoreSuppressionOfDeprecations="true">
        <include>
            <directory>%1$s</directory>
        </include>
    </source>
</phpunit>
',
            $this->workDir,
        ));

        [$exitCode, $output] = $this->runPhpUnit($projectDir, $this->workDir . '/phpunit.xml');

        $diagnostic = sprintf(
            "PHPUnit's deprecation gate did not fire after a kernel boot in the <bootstrap> file: FrameworkBundle::boot() "
            . "left Symfony's ErrorHandler in place and PHPUnit's own handler never activated. Is symfony/runtime installed?\n"
            . "Subprocess exit code: %d\nSubprocess output:\n%s",
            $exitCode,
            $output,
        );
        self::assertStringContainsString(self::PROBE_MESSAGE, $output, $diagnostic);
        self::assertStringContainsString('Deprecations: 1', $output, $diagnostic);
        self::assertNotSame(0, $exitCode, $diagnostic);
    }

    private function writeFile(
        string $name,
        string $content
    ): void {
        (new Filesystem())->dumpFile($this->workDir . '/' . $name, $content);
    }

    /**
     * @return array{int, string}
     */
    private function runPhpUnit(
        string $projectDir,
        string $configFile
    ): array {
        $process = proc_open(
            [PHP_BINARY, $projectDir . '/vendor/bin/phpunit', '--configuration', $configFile],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->workDir,
        );
        self::assertIsResource($process, 'Failed to start the PHPUnit subprocess.');

        $output = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
