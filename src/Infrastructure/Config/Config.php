<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Config;

use RuntimeException;

/**
 * All runtime configuration, read once from environment variables.
 *
 * Nothing secret lives in the code or in a committed file. Secrets come from either
 *   NAME_FILE  a path to a file holding the value (Docker/Kubernetes secrets, Vault agent), or
 *   NAME       the value itself (handy for local tools, weaker: env vars can leak into process dumps).
 * NAME_FILE wins when both are set.
 */
final readonly class Config
{
    public function __construct(
        public string $appEnv,
        public string $logLevel,
        public DatabaseConfig $database,
        public int $accessTokenTtlSeconds,
        public int $fakeGatewayLatencyMs,
        public string $kafkaBrokers,
        public string $kafkaTopic,
    ) {}

    public static function fromEnvironment(): self
    {
        return new self(
            appEnv: self::optional('APP_ENV', 'production'),
            logLevel: self::optional('LOG_LEVEL', 'info'),
            database: new DatabaseConfig(
                host: self::required('DB_HOST'),
                port: (int) self::optional('DB_PORT', '3306'),
                name: self::required('DB_NAME'),
                user: self::required('DB_USER'),
                password: self::secret('DB_PASSWORD'),
            ),
            accessTokenTtlSeconds: (int) self::optional('ACCESS_TOKEN_TTL_SECONDS', '3600'),
            fakeGatewayLatencyMs: (int) self::optional('FAKE_GATEWAY_LATENCY_MS', '0'),
            kafkaBrokers: self::optional('KAFKA_BROKERS', 'kafka:9092'),
            kafkaTopic: self::optional('KAFKA_TOPIC', 'billing.payment-events'),
        );
    }

    public function isProduction(): bool
    {
        return $this->appEnv === 'production';
    }

    private static function required(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            // Fail at startup with the variable's name, never its value.
            throw new RuntimeException(sprintf('Missing required environment variable %s.', $name));
        }

        return $value;
    }

    private static function optional(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }

    private static function secret(string $name): Secret
    {
        $path = getenv($name . '_FILE');
        if ($path === false || $path === '') {
            return new Secret(self::required($name));
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(sprintf('Secret file for %s is missing or not readable.', $name));
        }

        $value = rtrim((string) file_get_contents($path), "\r\n");
        if ($value === '') {
            throw new RuntimeException(sprintf('Secret file for %s is empty.', $name));
        }

        return new Secret($value);
    }
}
