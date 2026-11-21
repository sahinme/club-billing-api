<?php

declare(strict_types=1);

use ClubBilling\Application\Payment\PaymentGateway;
use ClubBilling\Infrastructure\Config\Config;
use ClubBilling\Infrastructure\Database\ConnectionFactory;
use ClubBilling\Infrastructure\Gateway\FakePaymentGateway;
use ClubBilling\Infrastructure\Logging\LoggerFactory;
use ClubBilling\Infrastructure\Logging\RequestContext;
use ClubBilling\Infrastructure\Messaging\KafkaMessagePublisher;
use ClubBilling\Infrastructure\Messaging\MessagePublisher;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;

/*
 * Dependency injection wiring. Most classes are autowired from their constructor types;
 * only things that need configuration or an interface binding are listed here.
 * Every entry is created lazily, on first use, and shared for the rest of the request.
 */

$builder = new ContainerBuilder();

$builder->addDefinitions([
    Config::class => static fn(): Config => Config::fromEnvironment(),

    PDO::class => static fn(Config $config): PDO => ConnectionFactory::create($config->database),

    LoggerInterface::class => static fn(Config $config, RequestContext $context): LoggerInterface
        => LoggerFactory::create($config->logLevel, $context),

    ResponseFactoryInterface::class => static fn(): ResponseFactoryInterface => new ResponseFactory(),

    // The only payment provider here is a fake one. A real one (Adyen, Stripe, ...) would be bound here.
    PaymentGateway::class => static fn(Config $config): PaymentGateway
        => new FakePaymentGateway($config->fakeGatewayLatencyMs),

    // Only the outbox relay worker asks for this, so the HTTP API never connects to Kafka.
    MessagePublisher::class => static fn(Config $config): MessagePublisher
        => new KafkaMessagePublisher($config->kafkaBrokers, $config->kafkaTopic),
]);

return $builder->build();
