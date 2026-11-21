<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Database;

use ClubBilling\Infrastructure\Config\DatabaseConfig;
use PDO;

final class ConnectionFactory
{
    public static function create(DatabaseConfig $config): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $config->host, $config->port, $config->name);

        $pdo = new PDO($dsn, $config->user, $config->password->reveal(), [
            // Every database error becomes an exception. No silent "false" return values.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Real server-side prepared statements: the SQL and the values travel separately,
            // so a value can never change the query (SQL injection).
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Store and compare every timestamp in UTC.
        $pdo->exec("SET time_zone = '+00:00'");

        return $pdo;
    }
}
