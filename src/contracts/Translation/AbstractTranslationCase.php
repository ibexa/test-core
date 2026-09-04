<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Translation;

use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Translation\Comparison\ChangeSet;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;

abstract class AbstractTranslationCase extends IbexaKernelTestCase
{
    /**
     * @return iterable<array{string}>
     */
    abstract public static function provideConfigNamesForTranslation(): iterable;

    /**
     * @dataProvider provideConfigNamesForTranslation
     */
    #[DataProvider('provideConfigNamesForTranslation')]
    final public function testTranslation(string $configName): void
    {
        $facade = $this->getTranslationService();
        $changeset = $facade->getChangeSet($configName);

        self::assertTranslationsUpToDate($changeset);
    }

    /**
     * Asserts that a changeset is empty, i.e. translations are up to date with the code:
     * no message ids were added or deleted, and no existing message's desc/meaning (the
     * code-derived default text) has drifted from what is currently on disk.
     *
     * Kept separate from testTranslation() (and static) so the assertion logic can be
     * unit-tested without booting a kernel.
     */
    public static function assertTranslationsUpToDate(ChangeSet $changeset): void
    {
        $addedMessages = $changeset->getAddedMessages();
        $deletedMessages = $changeset->getDeletedMessages();
        $changedMessages = $changeset->getChangedMessages();

        $message = 'Translations need to be regenerated.';
        if (count($addedMessages) > 0) {
            $message .= sprintf(
                "\nMissing translation with following ids:\n%s",
                implode(
                    "\n",
                    array_map(
                        static fn (Message $message): string => sprintf(
                            ' * %s [domain: %s]',
                            $message->getId(),
                            $message->getDomain()
                        ),
                        $addedMessages,
                    ),
                ),
            );
        }

        if (count($deletedMessages) > 0) {
            $message .= sprintf(
                "\nFollowing translation ids no longer exist:\n%s",
                implode(
                    "\n",
                    array_map(
                        static fn (Message $message): string => sprintf(
                            ' * %s [domain: %s]',
                            $message->getId(),
                            $message->getDomain()
                        ),
                        $deletedMessages,
                    ),
                ),
            );
        }

        if (count($changedMessages) > 0) {
            $message .= sprintf(
                "\nFollowing translation ids changed in code but the translation file is stale (run translation:extract):\n%s",
                implode(
                    "\n",
                    array_map(
                        static fn (Message $message): string => sprintf(
                            ' * %s [domain: %s]',
                            $message->getId(),
                            $message->getDomain()
                        ),
                        $changedMessages,
                    ),
                ),
            );
        }

        self::assertCount(0, array_merge($addedMessages, $deletedMessages, $changedMessages), $message);
    }

    private function getTranslationService(): TranslationService
    {
        $service = self::getContainer()->get(TranslationService::class);
        if (!$service instanceof TranslationService) {
            throw new LogicException(sprintf(
                'Invalid service acquired. Expected %s, received %s.',
                TranslationService::class,
                get_debug_type($service),
            ));
        }

        return $service;
    }
}
