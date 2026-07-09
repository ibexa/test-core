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

    public function testAddedMessagesFail(): void
    {
        $this->expectException(AssertionFailedError::class);

        AbstractTranslationCase::assertTranslationsUpToDate(
            new ChangeSet([Message::create('foo.bar')], [])
        );
    }

    public function testDeletedMessagesFail(): void
    {
        $this->expectException(AssertionFailedError::class);

        AbstractTranslationCase::assertTranslationsUpToDate(
            new ChangeSet([], [Message::create('foo.bar')])
        );
    }

    public function testChangedMessagesFail(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('foo.bar');

        AbstractTranslationCase::assertTranslationsUpToDate(
            new ChangeSet([], [], [Message::create('foo.bar')])
        );
    }
}
