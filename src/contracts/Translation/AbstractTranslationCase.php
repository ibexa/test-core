<?php

namespace Ibexa\Contracts\Test\Core\Translation;

use Ibexa\Contracts\Test\Core\IbexaKernelTestCase;
use JMS\TranslationBundle\Model\Message;

abstract class AbstractTranslationCase extends IbexaKernelTestCase
{
    abstract protected function getConfigName(): string;

    final public function testTranslation(): void
    {
        $facade = $this->getTranslationService();
        $changeset = $facade->getChangeSet('ibexa_activity_log');
        $facade->process('ibexa_activity_log');

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

    private function getTranslationService(): Translation
    {
        $service = self::getContainer()->get(Translation::class);
        if (!$service instanceof Translation) {
            throw new \LogicException(sprintf(
                'Invalid service acquired. Expected %s, received %s.',
                Translation::class,
                get_class($service),
            ));
        }

        return $service;
    }
}
