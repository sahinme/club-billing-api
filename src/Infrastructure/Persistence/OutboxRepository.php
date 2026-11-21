<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Persistence;

use ClubBilling\Infrastructure\Messaging\OutboxMessage;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PDO;
use Ramsey\Uuid\Uuid;

final readonly class OutboxRepository
{
    public function __construct(private PDO $pdo) {}

    /**
     * Stores an event in the same transaction as the business change.
     * Either both are saved or neither is, so we never publish an event for a payment that was rolled back,
     * and never lose the event of a payment that was saved.
     *
     * @param string $messageKey Kafka message key. Events with the same key stay in order.
     * @param array<string, mixed> $data
     */
    public function record(string $eventType, string $messageKey, array $data): void
    {
        $eventId = Uuid::uuid4()->toString();
        $envelope = [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'occurred_at' => new DateTimeImmutable('now', new DateTimeZone('UTC'))->format(DateTimeInterface::RFC3339_EXTENDED),
            'data' => $data,
        ];

        $this->pdo->prepare(
            'INSERT INTO outbox_messages (event_id, event_type, message_key, payload)
             VALUES (:event_id, :event_type, :message_key, :payload)',
        )->execute([
            'event_id' => $eventId,
            'event_type' => $eventType,
            'message_key' => $messageKey,
            'payload' => json_encode($envelope, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Locks up to $limit unpublished messages for the calling relay.
     * SKIP LOCKED: a second relay instance skips rows the first one is busy with instead of waiting.
     *
     * @return list<OutboxMessage>
     */
    public function lockUnpublished(int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, event_type, message_key, payload
               FROM outbox_messages
              WHERE published_at IS NULL
              ORDER BY id
              LIMIT :limit
                FOR UPDATE SKIP LOCKED',
        );
        $statement->bindValue('limit', $limit, PDO::PARAM_INT);
        $statement->execute();

        return array_values(array_map(
            static fn(array $row): OutboxMessage => new OutboxMessage(
                id: (int) $row['id'],
                eventType: (string) $row['event_type'],
                key: (string) $row['message_key'],
                payload: (string) $row['payload'],
            ),
            $statement->fetchAll(),
        ));
    }

    /** @param list<int> $ids */
    public function markPublished(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        // Only the number of "?" placeholders is built from code; the ids themselves are bound as parameters.
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $this->pdo->prepare(
            "UPDATE outbox_messages SET published_at = CURRENT_TIMESTAMP(6) WHERE id IN ({$placeholders})",
        )->execute($ids);
    }
}
