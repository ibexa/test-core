<?php

/**
 * @copyright Copyright (C) Ibexa AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 */
declare(strict_types=1);

namespace Ibexa\Contracts\Test\Core\Translation;

use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use JMS\TranslationBundle\Model\Message;
use LogicException;

abstract class AbstractTranslationCase extends IbexaKernelTestCase
{
    /**
     * @return iterable<array{string}>
     */
    abstract protected function provideConfigNamesForTranslation(): iterable;

    /**
     * @dataProvider provideConfigNamesForTranslation
     */
    final public function testTranslation(string $configName): void
    {
        $facade = $this->getTranslationService();
        $changeset = $facade->getChangeSet($configName);

        $addedMessages = $changeset->getAddedMessages();
        $deletedMessages = $changeset->getDeletedMessages();

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

        self::assertCount(0, array_merge($addedMessages, $deletedMessages), $message);
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
