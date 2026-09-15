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
 * ::class is resolved at compile time and loads nothing, which is what makes it usable on
 * {@see ServiceWithUninstalledParent}.
 *
 * @covers \Ibexa\Bundle\Test\Core\DependencyInjection\CompilerPass\PersistenceCheckCompilerPass
 */
final class PersistenceCheckCompilerPassTest extends TestCase
{
    private const EXPECTED_CONNECTION = 'ibexa.persistence.connection';

    private const OTHER_CONNECTION = 'doctrine.dbal.default_connection';

    /** A string, not an import: naming a non-existent class would need a second suppression. */
    private const MISSING_PARENT_CLASS = 'Ibexa\\Tests\\Bundle\\Test\\Core\\DependencyInjection\\CompilerPass\\Stub\\UninstalledDependency\\ParentFromUninstalledDependency';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Without this the pass returns early at its own guard and tests nothing.
        if (!class_exists(AbstractDoctrineDatabase::class)) {
            require_once __DIR__ . '/Stub/uninstalled_core_persistence.php';
        }
    }

    public function testResolvingAnUnloadableClassRaisesAnError(): void
    {
        try {
            // PHPStan cannot model that this throws rather than returning false.
            // @phpstan-ignore function.impossibleType
            $resolved = is_a(ServiceWithUninstalledParent::class, AbstractDoctrineDatabase::class, true);

            self::fail(sprintf(
                'Expected resolving "%s" to raise an Error, it returned %s instead.',
                ServiceWithUninstalledParent::class,
                var_export($resolved, true)
            ));
        } catch (\Error $e) {
            // PHP 7.4 renders Class 'X' not found, PHP 8 Class "X" not found.
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
