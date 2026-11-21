<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Messaging;

use RdKafka\Conf;
use RdKafka\Message;
use RdKafka\Producer;
use RdKafka\ProducerTopic;
use RuntimeException;

/** Publishes to Kafka with the php-rdkafka extension (librdkafka). */
final class KafkaMessagePublisher implements MessagePublisher
{
    private const int FLUSH_TIMEOUT_MS = 10_000;

    private readonly Producer $producer;

    private readonly ProducerTopic $topic;

    /** @var list<string> delivery errors reported by librdkafka during the current publish() */
    private array $deliveryErrors = [];

    public function __construct(string $brokers, string $topic)
    {
        $conf = new Conf();
        $conf->set('bootstrap.servers', $brokers);
        // Wait for all in-sync replicas, and let the broker drop duplicates caused by producer retries.
        $conf->set('acks', 'all');
        $conf->set('enable.idempotence', 'true');

        // librdkafka sends asynchronously and reports each message's fate here, during flush().
        $conf->setDrMsgCb(function (Producer $producer, Message $message): void {
            if ($message->err !== RD_KAFKA_RESP_ERR_NO_ERROR) {
                $this->deliveryErrors[] = $message->errstr() ?? 'unknown delivery error';
            }
        });

        $this->producer = new Producer($conf);
        $this->topic = $this->producer->newTopic($topic);
    }

    public function publish(array $messages): void
    {
        $this->takeDeliveryErrors();

        foreach ($messages as $message) {
            // RD_KAFKA_PARTITION_UA: let Kafka choose the partition from the key.
            $this->topic->producev(RD_KAFKA_PARTITION_UA, 0, $message->payload, $message->key, ['event_type' => $message->eventType]);
        }

        // Blocks until every message is acknowledged or failed; the callback above runs during this call.
        $result = $this->producer->flush(self::FLUSH_TIMEOUT_MS);
        if ($result !== RD_KAFKA_RESP_ERR_NO_ERROR) {
            throw new RuntimeException('Kafka flush timed out: ' . rd_kafka_err2str($result));
        }

        $errors = $this->takeDeliveryErrors();
        if ($errors !== []) {
            throw new RuntimeException('Kafka rejected messages: ' . implode('; ', array_unique($errors)));
        }
    }

    /** @return list<string> the errors collected so far; the list is cleared */
    private function takeDeliveryErrors(): array
    {
        $errors = $this->deliveryErrors;
        $this->deliveryErrors = [];

        return $errors;
    }
}
