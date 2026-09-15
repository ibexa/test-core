<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass;

use Ibexa\Bundle\Test\Core\DependencyInjection\CompilerPass\PersistenceCheckCompilerPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The fixture classes are written to disk and autoloaded at runtime rather than committed, so that
 * a class which deliberately cannot be loaded never reaches static analysis. For the same reason
 * every fixture is referred to by its name as a string, never as a symbol.
 *
 * @covers \Ibexa\Bundle\Test\Core\DependencyInjection\CompilerPass\PersistenceCheckCompilerPass
 */
final class PersistenceCheckCompilerPassTest extends TestCase
{
    private const EXPECTED_CONNECTION = 'ibexa.persistence.connection';

    private const GATEWAY_BASE_CLASS = 'Ibexa\\Contracts\\CorePersistence\\Gateway\\AbstractDoctrineDatabase';

    private const FIXTURE_NAMESPACE = 'Ibexa\\Tests\\Bundle\\Test\\Core\\PersistenceCheckFixture';

    /** Extends NOT_INSTALLED_CLASS, whose file is never written — the segmentation case. */
    private const UNLOADABLE_CLASS = self::FIXTURE_NAMESPACE . '\\ServiceWithUninstalledParent';

    /** Stands in for a parent coming from a dependency that is not installed. */
    private const NOT_INSTALLED_CLASS = self::FIXTURE_NAMESPACE . '\\ParentFromUninstalledDependency';

    /** Declares itself and only then fails, so the class is loaded by the time the Error surfaces. */
    private const FAILING_AFTER_DECLARATION_CLASS = self::FIXTURE_NAMESPACE . '\\ServiceFailingAfterDeclaration';

    private const GATEWAY_CLASS = self::FIXTURE_NAMESPACE . '\\Gateway';

    private const PLAIN_CLASS = self::FIXTURE_NAMESPACE . '\\PlainService';

    /** @var string */
    private static $fixtureDir;

    /** @var callable|null */
    private static $autoloader;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$fixtureDir = sprintf('%s/ibexa-persistence-check-%d', sys_get_temp_dir(), getmypid());
        if (!is_dir(self::$fixtureDir) && !mkdir(self::$fixtureDir, 0777, true) && !is_dir(self::$fixtureDir)) {
            self::fail(sprintf('Could not create fixture directory "%s".', self::$fixtureDir));
        }

        // Deliberately `require`, not `require_once`: a class whose parent is missing never gets
        // declared, so every resolution attempt has to fail again instead of silently succeeding
        // on the second try. That keeps the test cases independent of execution order.
        self::$autoloader = static function (string $class): void {
            $file = self::fixtureFile($class);
            if (is_file($file)) {
                require $file;
            }
        };
        spl_autoload_register(self::$autoloader);

        // ibexa/core-persistence is not a dependency of this package, so the class the pass checks
        // against is absent and the pass would otherwise return early. Provide it when missing.
        if (!class_exists(self::GATEWAY_BASE_CLASS)) {
            self::writeFixture(
                self::GATEWAY_BASE_CLASS,
                'abstract class AbstractDoctrineDatabase {}',
                'Ibexa\\Contracts\\CorePersistence\\Gateway'
            );
        }

        self::writeFixture(self::GATEWAY_CLASS, sprintf(
            'class Gateway extends \\%s {}',
            self::GATEWAY_BASE_CLASS
        ));
        self::writeFixture(self::PLAIN_CLASS, 'class PlainService {}');
        self::writeFixture(self::UNLOADABLE_CLASS, sprintf(
            'class ServiceWithUninstalledParent extends \\%s {}',
            self::NOT_INSTALLED_CLASS
        ));
        self::writeFixture(
            self::FAILING_AFTER_DECLARATION_CLASS,
            "class ServiceFailingAfterDeclaration {}\n\nthrow new \\Error('failure unrelated to loading');"
        );
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$autoloader !== null) {
            spl_autoload_unregister(self::$autoloader);
            self::$autoloader = null;
        }

        foreach (glob(self::$fixtureDir . '/*.php') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir(self::$fixtureDir)) {
            rmdir(self::$fixtureDir);
        }

        parent::tearDownAfterClass();
    }

    /**
     * Establishes the premise of the fix: resolving such a class name raises an Error naming the
     * *parent*, which is what took down container compilation before it was handled.
     */
    public function testResolvingAnUnloadableClassRaisesAnError(): void
    {
        self::assertFileDoesNotExist(
            self::fixtureFile(self::NOT_INSTALLED_CLASS),
            'The parent is meant to be missing, standing in for an uninstalled dependency.'
        );

        try {
            $resolved = is_a(self::UNLOADABLE_CLASS, self::GATEWAY_BASE_CLASS, true);

            self::fail(sprintf(
                'Expected resolving "%s" to raise an Error, it returned %s instead.',
                self::UNLOADABLE_CLASS,
                var_export($resolved, true)
            ));
        } catch (\Error $e) {
            // Asserted on the class name rather than the whole message: PHP 7.4 renders it as
            // Class 'X' not found and PHP 8 as Class "X" not found.
            self::assertStringContainsString(
                self::NOT_INSTALLED_CLASS,
                $e->getMessage(),
                'The Error names the missing parent, not the service class itself.'
            );
            self::assertStringNotContainsString(
                self::UNLOADABLE_CLASS,
                $e->getMessage(),
                'The service class itself is resolvable; it is its parent that is not.'
            );
        }
    }

    public function testSkipsServiceWhoseClassCannotBeLoaded(): void
    {
        $container = new ContainerBuilder();
        $container->register('service_with_uninstalled_parent', self::UNLOADABLE_CLASS);

        (new PersistenceCheckCompilerPass())->process($container);

        self::assertFalse(
            class_exists(self::UNLOADABLE_CLASS, false),
            'The class still cannot be loaded, so the pass got past it without resolving it.'
        );
    }

    /**
     * The unloadable class must not cut the run short: a genuinely misconfigured gateway declared
     * after it is still reported.
     */
    public function testKeepsCheckingDefinitionsAfterAnUnloadableOne(): void
    {
        $container = new ContainerBuilder();
        $container->register('service_with_uninstalled_parent', self::UNLOADABLE_CLASS);
        $container->register('misconfigured_gateway', self::GATEWAY_CLASS)
            ->setArgument('$connection', new Reference('doctrine.dbal.default_connection'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Service definition "misconfigured_gateway" contains reference to "doctrine.dbal.default_connection" as connection argument.');

        (new PersistenceCheckCompilerPass())->process($container);
    }

    public function testReportsGatewayWithUnexpectedConnection(): void
    {
        $container = new ContainerBuilder();
        $container->register('misconfigured_gateway', self::GATEWAY_CLASS)
            ->setArgument('$connection', new Reference('doctrine.dbal.default_connection'));

        $this->expectException(\LogicException::class);

        (new PersistenceCheckCompilerPass())->process($container);
    }

    public function testAcceptsGatewayWithExpectedConnection(): void
    {
        $container = new ContainerBuilder();
        $container->register('gateway', self::GATEWAY_CLASS)
            ->setArgument('$connection', new Reference(self::EXPECTED_CONNECTION));
        $container->register('plain_service', self::PLAIN_CLASS);

        (new PersistenceCheckCompilerPass())->process($container);

        self::assertTrue($container->hasDefinition('gateway'));
    }

    /**
     * An Error raised for any reason other than the class failing to load is none of the pass's
     * business and must not be swallowed.
     */
    public function testRethrowsErrorRaisedByAClassThatDidLoad(): void
    {
        $container = new ContainerBuilder();
        $container->register('service_failing_after_declaration', self::FAILING_AFTER_DECLARATION_CLASS);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('failure unrelated to loading');

        (new PersistenceCheckCompilerPass())->process($container);
    }

    public function testIgnoresDefinitionsWithoutAClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('classless', new Definition());

        (new PersistenceCheckCompilerPass())->process($container);

        self::assertNull($container->getDefinition('classless')->getClass());
    }

    private static function writeFixture(
        string $class,
        string $body,
        ?string $namespace = null
    ): void {
        $namespace = $namespace ?? self::FIXTURE_NAMESPACE;

        file_put_contents(
            self::fixtureFile($class),
            sprintf("<?php\n\ndeclare(strict_types=1);\n\nnamespace %s;\n\n%s\n", $namespace, $body)
        );
    }

    private static function fixtureFile(string $class): string
    {
        return sprintf('%s/%s.php', self::$fixtureDir, str_replace('\\', '_', $class));
    }
}
