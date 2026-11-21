<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Messaging;

final readonly class OutboxMessage
{
    public function __construct(
        public int $id,
        public string $eventType,
        /** Kafka message key: all events of one invoice go to the same partition, so they stay in order. */
        public string $key,
        /** The full JSON event, exactly as consumers receive it. */
        public string $payload,
    ) {}
}
