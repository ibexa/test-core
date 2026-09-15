<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass;

use Ibexa\Bundle\Test\Core\DependencyInjection\CompilerPass\PersistenceCheckCompilerPass;
use Ibexa\Contracts\CorePersistence\Gateway\AbstractDoctrineDatabase;
use Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub\Gateway;
use Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub\PlainService;
use Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub\ServiceFailingAfterDeclaration;
use Ibexa\Tests\Bundle\Test\Core\DependencyInjection\CompilerPass\Stub\ServiceWithUninstalledParent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The service classes used here live in Stub/ next to this test. Referring to them by ::class is
 * safe: it is resolved at compile time and does not load anything, which matters because
 * {@see ServiceWithUninstalledParent} cannot be loaded at all by design.
 *
 * @covers \Ibexa\Bundle\Test\Core\DependencyInjection\CompilerPass\PersistenceCheckCompilerPass
 */
final class PersistenceCheckCompilerPassTest extends TestCase
{
    private const EXPECTED_CONNECTION = 'ibexa.persistence.connection';

    private const OTHER_CONNECTION = 'doctrine.dbal.default_connection';

    /**
     * Spelled out as a string rather than imported: there is deliberately no such class, so naming
     * it as a symbol would need a second suppression. This keeps the only one confined to
     * {@see ServiceWithUninstalledParent}, the fixture that declares the broken inheritance.
     */
    private const MISSING_PARENT_CLASS = 'Ibexa\\Tests\\Bundle\\Test\\Core\\DependencyInjection\\CompilerPass\\Stub\\UninstalledDependency\\ParentFromUninstalledDependency';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // ibexa/core-persistence is not a dependency of this package, so the class the pass checks
        // against has to be supplied or the pass returns early and tests nothing.
        if (!class_exists(AbstractDoctrineDatabase::class)) {
            require_once __DIR__ . '/Stub/uninstalled_core_persistence.php';
        }
    }

    /**
     * Establishes the premise of the fix: resolving such a class name raises an Error naming the
     * *parent*, which is what took down container compilation before it was handled.
     */
    public function testResolvingAnUnloadableClassRaisesAnError(): void
    {
        try {
            // PHPStan sees an unknown parent and concludes the call can only return false. What it
            // cannot model is that resolving the name throws before returning anything at all,
            // which is the behaviour under test.
            // @phpstan-ignore function.impossibleType
            $resolved = is_a(ServiceWithUninstalledParent::class, AbstractDoctrineDatabase::class, true);

            self::fail(sprintf(
                'Expected resolving "%s" to raise an Error, it returned %s instead.',
                ServiceWithUninstalledParent::class,
                var_export($resolved, true)
            ));
        } catch (\Error $e) {
            // Asserted on the class name rather than the whole message: PHP 7.4 renders it as
            // Class 'X' not found and PHP 8 as Class "X" not found.
            self::assertStringContainsString(
                self::MISSING_PARENT_CLASS,
                $e->getMessage(),
                'The Error names the missing parent, not the service class itself.'
            );
            self::assertStringNotContainsString(
                ServiceWithUninstalledParent::class,
                $e->getMessage(),
                'The service class itself is resolvable; it is its parent that is not.'
            );
        }
    }

    public function testSkipsServiceWhoseClassCannotBeLoaded(): void
    {
        $container = new ContainerBuilder();
        $container->register('service_with_uninstalled_parent', ServiceWithUninstalledParent::class);

        (new PersistenceCheckCompilerPass())->process($container);

        self::assertFalse(
            class_exists(ServiceWithUninstalledParent::class, false),
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
        $container->register('service_with_uninstalled_parent', ServiceWithUninstalledParent::class);
        $container->register('misconfigured_gateway', Gateway::class)
            ->setArgument('$connection', new Reference(self::OTHER_CONNECTION));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(sprintf(
            'Service definition "misconfigured_gateway" contains reference to "%s" as connection argument.',
            self::OTHER_CONNECTION
        ));

        (new PersistenceCheckCompilerPass())->process($container);
    }

    public function testReportsGatewayWithUnexpectedConnection(): void
    {
        $container = new ContainerBuilder();
        $container->register('misconfigured_gateway', Gateway::class)
            ->setArgument('$connection', new Reference(self::OTHER_CONNECTION));

        $this->expectException(\LogicException::class);

        (new PersistenceCheckCompilerPass())->process($container);
    }

    public function testAcceptsGatewayWithExpectedConnection(): void
    {
        $container = new ContainerBuilder();
        $container->register('gateway', Gateway::class)
            ->setArgument('$connection', new Reference(self::EXPECTED_CONNECTION));
        $container->register('plain_service', PlainService::class);

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
        $container->register('service_failing_after_declaration', ServiceFailingAfterDeclaration::class);

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
}
