<?php

declare(strict_types=1);

// Applies pending database migrations. Run by the "migrate" service in compose.yaml.

use ClubBilling\Infrastructure\Database\Migrator;
use Psr\Container\ContainerInterface;

require __DIR__ . '/../vendor/autoload.php';

/** @var ContainerInterface $container */
$container = require __DIR__ . '/../config/container.php';

/** @var PDO $pdo */
$pdo = $container->get(PDO::class);

$applied = new Migrator($pdo, __DIR__ . '/../database/migrations')->migrate();

echo $applied === [] ? "Database schema is up to date.\n" : 'Applied: ' . implode(', ', $applied) . "\n";
