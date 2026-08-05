<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Tests\Test\Core\Translation;

use Ibexa\Contracts\Test\Core\Translation\AbstractTranslationCase;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Translation\Comparison\ChangeSet;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

final class AbstractTranslationCaseTest extends TestCase
{
    public function testEmptyChangeSetPasses(): void
    {
        AbstractTranslationCase::assertTranslationsUpToDate(new ChangeSet([], []));

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider provideChangeSetsWithPendingMessages
     */
    public function testChangeSetWithPendingMessagesFails(
        ChangeSet $changeSet,
        string $expectedMessage
    ): void {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage($expectedMessage);

        AbstractTranslationCase::assertTranslationsUpToDate($changeSet);
    }

    /**
     * @return iterable<string, array{ChangeSet, string}>
     */
    public static function provideChangeSetsWithPendingMessages(): iterable
    {
        yield 'added message' => [
            new ChangeSet([Message::create('foo.added')], []),
            'Missing translation with following ids',
        ];

        yield 'deleted message' => [
            new ChangeSet([], [Message::create('foo.deleted')]),
            'Following translation ids no longer exist',
        ];

        yield 'changed message' => [
            new ChangeSet([], [], [Message::create('foo.changed')]),
            'Following translation ids changed in code',
        ];
    }
}
