<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core;

use Doctrine\DBAL\Connection;
use Ibexa\Contracts\Core\Persistence\TransactionHandler;
use Ibexa\Contracts\Core\Repository\ContentService;
use Ibexa\Contracts\Core\Repository\ContentTypeService;
use Ibexa\Contracts\Core\Repository\LanguageService;
use Ibexa\Contracts\Core\Repository\LocationService;
use Ibexa\Contracts\Core\Repository\ObjectStateService;
use Ibexa\Contracts\Core\Repository\PermissionResolver;
use Ibexa\Contracts\Core\Repository\RoleService;
use Ibexa\Contracts\Core\Repository\SearchService;
use Ibexa\Contracts\Core\Repository\SectionService;
use Ibexa\Contracts\Core\Repository\UserPreferenceService;
use Ibexa\Contracts\Core\Repository\UserService;
use Ibexa\Contracts\Core\Test\IbexaTestKernelInterface;
use Ibexa\Contracts\Core\Test\Persistence\Fixture\FixtureImporter;
use Ibexa\Core\Repository\Values\User\UserReference;
use Ibexa\Tests\Core\Repository\LegacySchemaImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @experimental
 *
 * @internal use IbexaTestCoreInterface instead.
 *
 * @see \Ibexa\Contracts\Test\Core\IbexaTestCoreInterface
 */
final class IbexaTestCore implements IbexaTestCoreInterface
{
    public const ANONYMOUS_USER_ID = 10;
    public const ADMIN_USER_ID = 14;

    private ContainerInterface $container;

    private IbexaTestKernelInterface $kernel;

    public function __construct(ContainerInterface $container, IbexaTestKernelInterface $kernel)
    {
        $this->container = $container;
        $this->kernel = $kernel;
    }

    public function loadSchema(): void
    {
        /** @var \Ibexa\Tests\Core\Repository\LegacySchemaImporter $schemaImporter */
        $schemaImporter = $this->container->get(LegacySchemaImporter::class);
        foreach ($this->getSchemaFiles() as $schemaFile) {
            $schemaImporter->importSchema($schemaFile);
        }
    }

    /**
     * @return iterable<string>
     */
    public function getSchemaFiles(): iterable
    {
        yield from $this->kernel->getSchemaFiles();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function loadFixtures(?callable $postLoadFixtures = null): void
    {
        /** @var \Ibexa\Contracts\Core\Test\Persistence\Fixture\FixtureImporter $fixtureImporter */
        $fixtureImporter = $this->container->get(FixtureImporter::class);
        foreach ($this->getFixtures() as $fixture) {
            $fixtureImporter->import($fixture);
        }

        if (null !== $postLoadFixtures) {
            $postLoadFixtures();
        }
    }

    /**
     * @return iterable<\Ibexa\Contracts\Core\Test\Persistence\Fixture>
     */
    public function getFixtures(): iterable
    {
        yield from $this->kernel->getFixtures();
    }

    /**
     * @template T of object
     *
     * @phpstan-param class-string<T> $className
     *
     * @return T
     */
    public function getServiceByClassName(string $className, ?string $id = null, bool $prefix = true): object
    {
        $serviceId = $this->getTestServiceId($id, $className, $prefix);
        $service = $this->container->get($serviceId);
        assert(is_object($service) && is_a($service, $className));

        return $service;
    }

    private function getTestServiceId(?string $id, string $className, bool $prefix): string
    {
        $id = $id ?? $className;

        return $prefix ? $this->kernel::getAliasServiceId($id) : $id;
    }

    public function getDoctrineConnection(): Connection
    {
        return $this->getServiceByClassName(Connection::class);
    }

    public function getContentTypeService(): ContentTypeService
    {
        return $this->getServiceByClassName(ContentTypeService::class);
    }

    public function getContentService(): ContentService
    {
        return $this->getServiceByClassName(ContentService::class);
    }

    public function getLocationService(): LocationService
    {
        return $this->getServiceByClassName(LocationService::class);
    }

    public function getPermissionResolver(): PermissionResolver
    {
        return $this->getServiceByClassName(PermissionResolver::class);
    }

    public function getRoleService(): RoleService
    {
        return $this->getServiceByClassName(RoleService::class);
    }

    public function getSearchService(): SearchService
    {
        return $this->getServiceByClassName(SearchService::class);
    }

    public function getTransactionHandler(): TransactionHandler
    {
        return $this->getServiceByClassName(TransactionHandler::class);
    }

    public function getUserService(): UserService
    {
        return $this->getServiceByClassName(UserService::class);
    }

    public function getObjectStateService(): ObjectStateService
    {
        return $this->getServiceByClassName(ObjectStateService::class);
    }

    public function getLanguageService(): LanguageService
    {
        return $this->getServiceByClassName(LanguageService::class);
    }

    public function getSectionService(): SectionService
    {
        return $this->getServiceByClassName(SectionService::class);
    }

    public function getUserPreferenceService(): UserPreferenceService
    {
        return $this->getServiceByClassName(UserPreferenceService::class);
    }

    public function setAnonymousUser(): void
    {
        $this->getPermissionResolver()->setCurrentUserReference(new UserReference(self::ANONYMOUS_USER_ID));
    }

    public function setAdministratorUser(): void
    {
        $this->getPermissionResolver()->setCurrentUserReference(new UserReference(self::ADMIN_USER_ID));
    }
}
