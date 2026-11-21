<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Messaging;

interface MessagePublisher
{
    /**
     * Publishes all messages and returns only when the broker has confirmed every one of them.
     *
     * @param list<OutboxMessage> $messages
     * @throws \RuntimeException when any message could not be delivered
     */
    public function publish(array $messages): void;
}
