<?php

declare(strict_types=1);

namespace ClubBilling\Http\Controller;

use ClubBilling\Http\Response\JsonResponder;
use ClubBilling\Infrastructure\Config\Config;
use ClubBilling\Infrastructure\Database\ConnectionFactory;
use PDOException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/** GET /health (public). Used by the load balancer / Kubernetes readiness probe. */
final readonly class HealthController
{
    public function __construct(
        private Config $config,
        private JsonResponder $responder,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(): ResponseInterface
    {
        try {
            // Opens its own connection (instead of the shared PDO) so a dead database
            // gives a clean 503 here instead of failing while the controller is being built.
            ConnectionFactory::create($this->config->database)->query('SELECT 1');
        } catch (PDOException $error) {
            $this->logger->error('health.database_unreachable', ['exception' => $error]);

            // Say what is down, but no host names or connection details.
            return $this->responder->json(['status' => 'unavailable', 'database' => 'down'], 503);
        }

        return $this->responder->json(['status' => 'ok', 'database' => 'up']);
    }
}
