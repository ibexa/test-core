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

/**
 * @experimental
 */
interface IbexaTestCoreInterface
{
    /**
     * @deprecated 4.6.33 The "IbexaTestCoreInterface::loadSchema()" method is deprecated, will be
     *   removed in 6.0. Installing the schema from a test case rules out running tests inside a
     *   transaction; the Bootstrapper's
     *   {@see \Ibexa\Contracts\Test\Core\Bootstrapper\DatabaseSchemaHook} does it once per run
     *   instead, which is what DAMADoctrineTestBundle needs.
     */
    public function loadSchema(): void;

    /**
     * @deprecated 4.6.33 The "IbexaTestCoreInterface::getSchemaFiles()" method is deprecated, will be
     *   removed in 6.0. It exists only to feed {@see self::loadSchema()}.
     *
     * @return iterable<string>
     */
    public function getSchemaFiles(): iterable;

    /**
     * @deprecated 4.6.33 The "IbexaTestCoreInterface::loadFixtures()" method is deprecated, will be
     *   removed in 6.0. Importing fixtures from a test case rules out running tests inside a
     *   transaction; the Bootstrapper's {@see \Ibexa\Contracts\Test\Core\Bootstrapper\BaseFixtureHook}
     *   and {@see \Ibexa\Contracts\Test\Core\Bootstrapper\FixtureHook} do it once per run instead,
     *   which is what DAMADoctrineTestBundle needs.
     */
    public function loadFixtures(): void;

    /**
     * @deprecated 4.6.33 The "IbexaTestCoreInterface::getFixtures()" method is deprecated, will be
     *   removed in 6.0. It exists only to feed {@see self::loadFixtures()}.
     *
     * @return iterable<\Ibexa\Contracts\Core\Test\Persistence\Fixture>
     */
    public function getFixtures(): iterable;

    /**
     * @template T of object
     *
     * @phpstan-param class-string<T> $className
     *
     * @return T
     */
    public function getServiceByClassName(string $className, ?string $id = null, bool $prefix = true): object;

    public function getDoctrineConnection(): Connection;

    public function getContentTypeService(): ContentTypeService;

    public function getContentService(): ContentService;

    public function getLocationService(): LocationService;

    public function getPermissionResolver(): PermissionResolver;

    public function getRoleService(): RoleService;

    public function getSearchService(): SearchService;

    public function getTransactionHandler(): TransactionHandler;

    public function getUserService(): UserService;

    public function getObjectStateService(): ObjectStateService;

    public function getLanguageService(): LanguageService;

    public function getSectionService(): SectionService;

    public function getUserPreferenceService(): UserPreferenceService;

    public function setAnonymousUser(): void;

    public function setAdministratorUser(): void;
}
