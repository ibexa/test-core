<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Integration\Test\Core;

use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use Ibexa\Contracts\Test\Core\IbexaTestCore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[Group('integration')]
#[CoversClass(IbexaTestCore::class)]
final class IbexaTestCoreTest extends IbexaKernelTestCase
{
    public function testSetsAnonymousUser(): void
    {
        $ibexaTestCore = $this->getIbexaTestCore();
        $ibexaTestCore->setAdministratorUser();

        $ibexaTestCore->setAnonymousUser();

        self::assertSame(
            IbexaTestCore::ANONYMOUS_USER_ID,
            $ibexaTestCore->getPermissionResolver()->getCurrentUserReference()->getUserId()
        );
    }

    public function testSetsAdministratorUser(): void
    {
        $ibexaTestCore = $this->getIbexaTestCore();
        $ibexaTestCore->setAnonymousUser();

        $ibexaTestCore->setAdministratorUser();

        self::assertSame(
            IbexaTestCore::ADMIN_USER_ID,
            $ibexaTestCore->getPermissionResolver()->getCurrentUserReference()->getUserId()
        );
    }

    public function testCurrentUserWithPoliciesHasOnlyTheGivenPolicies(): void
    {
        $ibexaTestCore = $this->getIbexaTestCore();

        $user = $ibexaTestCore->setCurrentUserWithPolicies(
            'reader',
            [$ibexaTestCore->getRoleService()->newPolicyCreateStruct('content', 'read')]
        );

        $permissionResolver = $ibexaTestCore->getPermissionResolver();
        self::assertSame($user->getUserId(), $permissionResolver->getCurrentUserReference()->getUserId());
        self::assertTrue($permissionResolver->hasAccess('content', 'read'));
        self::assertFalse($permissionResolver->hasAccess('content', 'edit'));
    }

    public function testCreatesUserWithoutChangingTheCurrentUser(): void
    {
        $ibexaTestCore = $this->getIbexaTestCore();
        $ibexaTestCore->setAnonymousUser();

        $user = $ibexaTestCore->createUserWithPolicies('without-policies');

        $permissionResolver = $ibexaTestCore->getPermissionResolver();
        self::assertSame(
            IbexaTestCore::ANONYMOUS_USER_ID,
            $permissionResolver->getCurrentUserReference()->getUserId()
        );
        $ibexaTestCore->setCurrentUser($user);
        self::assertFalse($permissionResolver->hasAccess('content', 'read'));
    }
}
