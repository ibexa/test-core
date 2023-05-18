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
use Ibexa\Contracts\Core\Repository\UserService;

/**
 * @experimental
 */
interface IbexaTestCoreInterface
{
    public function loadSchema(): void;

    /**
     * @return iterable<string>
     */
    public function getSchemaFiles(): iterable;

    public function loadFixtures(): void;

    /**
     * @return iterable<\Ibexa\Contracts\Core\Test\Persistence\Fixture>
     */
    public function getFixtures(): iterable;

    /**
     * @template T of object
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

    public function setAnonymousUser(): void;

    public function setAdministratorUser(): void;
}
