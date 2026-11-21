<?php

declare(strict_types=1);

// Long-running worker: publishes outbox_messages to Kafka. Started by docker compose (service "outbox-relay").

use ClubBilling\Infrastructure\Messaging\OutboxRelay;
use Psr\Container\ContainerInterface;

require __DIR__ . '/../vendor/autoload.php';

/** @var ContainerInterface $container */
$container = require __DIR__ . '/../config/container.php';

$container->get(OutboxRelay::class)->run();
