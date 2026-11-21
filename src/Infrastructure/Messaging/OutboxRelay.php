<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Messaging;

use ClubBilling\Infrastructure\Database\TransactionManager;
use ClubBilling\Infrastructure\Persistence\OutboxRepository;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Background worker: moves events from the outbox_messages table to Kafka.
 *
 * Why not publish to Kafka directly inside the HTTP request? Because "write to DB" and "write to Kafka"
 * cannot be one atomic step (the dual-write problem). If the DB commit succeeds and Kafka is down,
 * the event is lost; the other way round, we announce a payment that never happened.
 * With the outbox, the event is part of the DB transaction, and this worker delivers it later.
 *
 * Delivery is at-least-once: if we crash after Kafka confirmed but before marking rows published,
 * those events are sent again. Consumers must deduplicate on event_id.
 */
final class OutboxRelay
{
    private bool $running = true;

    public function __construct(
        private readonly TransactionManager $transactions,
        private readonly OutboxRepository $outbox,
        private readonly MessagePublisher $publisher,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(int $batchSize = 100, int $idleSleepMs = 500, int $errorSleepMs = 5_000): void
    {
        // Stop cleanly on "docker stop" / Kubernetes pod shutdown, after the current batch.
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn() => $this->running = false);
        pcntl_signal(SIGINT, fn() => $this->running = false);

        $this->logger->info('outbox_relay.started');

        while ($this->running) {
            try {
                $published = $this->relayBatch($batchSize);
            } catch (Throwable $error) {
                // Kafka or the database is unavailable. Rows stay unpublished and are retried after a pause.
                $this->logger->error('outbox_relay.batch_failed', ['exception' => $error]);
                usleep($errorSleepMs * 1000);
                continue;
            }

            if ($published === 0) {
                usleep($idleSleepMs * 1000);
            }
        }

        $this->logger->info('outbox_relay.stopped');
    }

    /** @return int number of messages published */
    public function relayBatch(int $batchSize): int
    {
        return $this->transactions->run(function () use ($batchSize): int {
            $messages = $this->outbox->lockUnpublished($batchSize);
            if ($messages === []) {
                return 0;
            }

            // Throws if Kafka did not confirm. The transaction then rolls back and the rows are retried.
            $this->publisher->publish($messages);
            $this->outbox->markPublished(array_map(static fn(OutboxMessage $m): int => $m->id, $messages));

            $this->logger->info('outbox_relay.published', ['count' => count($messages)]);

            return count($messages);
        });
    }
}
