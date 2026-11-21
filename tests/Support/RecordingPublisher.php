<?php

declare(strict_types=1);

namespace ClubBilling\Tests\Support;

use ClubBilling\Infrastructure\Messaging\MessagePublisher;
use ClubBilling\Infrastructure\Messaging\OutboxMessage;
use RuntimeException;

/** Test double for Kafka: remembers what was published, or fails on demand. */
final class RecordingPublisher implements MessagePublisher
{
    /** @var list<OutboxMessage> */
    public array $messages = [];

    public function __construct(private readonly bool $fail = false) {}

    public function publish(array $messages): void
    {
        if ($this->fail) {
            throw new RuntimeException('Kafka is down.');
        }
        $this->messages = [...$this->messages, ...$messages];
    }
}
