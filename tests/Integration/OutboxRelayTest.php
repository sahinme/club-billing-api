<?php

declare(strict_types=1);

namespace ClubBilling\Tests\Integration;

use ClubBilling\Database\Seed\DemoDataSeeder as Demo;
use ClubBilling\Infrastructure\Database\TransactionManager;
use ClubBilling\Infrastructure\Messaging\MessagePublisher;
use ClubBilling\Infrastructure\Messaging\OutboxRelay;
use ClubBilling\Infrastructure\Persistence\OutboxRepository;
use ClubBilling\Tests\Support\RecordingPublisher;
use Psr\Log\NullLogger;
use RuntimeException;

final class OutboxRelayTest extends ApiTestCase
{
    public function testPublishesPendingEventsAndMarksThemPublished(): void
    {
        $this->pay($this->loginAs(Demo::FRONT_DESK), Demo::INVOICE_ANNA_OPEN, 4999);
        $publisher = new RecordingPublisher();

        $published = $this->relayWith($publisher)->relayBatch(10);

        self::assertSame(1, $published);
        self::assertSame((string) Demo::INVOICE_ANNA_OPEN, $publisher->messages[0]->key);
        self::assertSame(0, $this->countRows('outbox_messages', 'published_at IS NULL'));
    }

    public function testKeepsEventsWhenKafkaIsDown(): void
    {
        $this->pay($this->loginAs(Demo::FRONT_DESK), Demo::INVOICE_ANNA_OPEN, 4999);
        $publisher = new RecordingPublisher(fail: true);

        try {
            $this->relayWith($publisher)->relayBatch(10);
            self::fail('Expected the relay to fail.');
        } catch (RuntimeException) {
        }

        // Nothing was lost: the event is still waiting for the next attempt.
        self::assertSame(1, $this->countRows('outbox_messages', 'published_at IS NULL'));
    }

    private function relayWith(MessagePublisher $publisher): OutboxRelay
    {
        return new OutboxRelay(
            $this->container->get(TransactionManager::class),
            $this->container->get(OutboxRepository::class),
            $publisher,
            new NullLogger(),
        );
    }
}
