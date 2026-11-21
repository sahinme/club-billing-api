<?php

declare(strict_types=1);

namespace ClubBilling\Tests\Integration;

use ClubBilling\Database\Seed\DemoDataSeeder;
use ClubBilling\Infrastructure\Database\Migrator;
use DI\Container;
use PDO;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

/**
 * Runs real HTTP requests through the full app (middleware, routing, controllers, services)
 * against a real MariaDB test database. Only the network hop through nginx is skipped.
 * Every test starts from the same freshly seeded data.
 */
abstract class ApiTestCase extends TestCase
{
    private static bool $migrated = false;

    protected Container $container;

    protected PDO $pdo;

    /** @var App<ContainerInterface> */
    private App $app;

    protected function setUp(): void
    {
        $this->container = require __DIR__ . '/../../config/container.php';
        $this->pdo = $this->container->get(PDO::class);

        if (!self::$migrated) {
            new Migrator($this->pdo, __DIR__ . '/../../database/migrations')->migrate();
            self::$migrated = true;
        }
        $this->container->get(DemoDataSeeder::class)->run();

        $this->app = (require __DIR__ . '/../../config/app.php')($this->container);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     */
    protected function request(string $method, string $path, ?array $body = null, array $headers = []): ResponseInterface
    {
        $request = new ServerRequestFactory()->createServerRequest($method, $path);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody(new StreamFactory()->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        }

        return $this->app->handle($request);
    }

    protected function loginAs(string $email): string
    {
        $response = $this->request('POST', '/v1/auth/login', ['email' => $email, 'password' => DemoDataSeeder::PASSWORD]);
        self::assertSame(200, $response->getStatusCode());

        return (string) self::json($response)['access_token'];
    }

    protected function pay(string $token, int $invoiceId, int $amount, string $paymentMethod = 'pm_card_ok', ?string $idempotencyKey = null): ResponseInterface
    {
        return $this->request(
            'POST',
            "/v1/invoices/{$invoiceId}/payments",
            ['amount' => $amount, 'currency' => 'EUR', 'payment_method' => $paymentMethod],
            ['Authorization' => "Bearer {$token}", 'Idempotency-Key' => $idempotencyKey ?? bin2hex(random_bytes(8))],
        );
    }

    /** @return array<string, mixed> */
    protected static function json(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        /** @var array<string, mixed> $data */
        return $data;
    }

    protected function countRows(string $table, string $where = '1 = 1'): int
    {
        // Test helper: table and condition are literals written in the tests, never outside input.
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        $statement->execute();

        return (int) $statement->fetchColumn();
    }
}
