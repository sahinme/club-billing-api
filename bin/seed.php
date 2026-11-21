<?php

declare(strict_types=1);

// Resets the database to the demo data. Development only.

use ClubBilling\Database\Seed\DemoDataSeeder;
use ClubBilling\Infrastructure\Config\Config;
use Psr\Container\ContainerInterface;

require __DIR__ . '/../vendor/autoload.php';

/** @var ContainerInterface $container */
$container = require __DIR__ . '/../config/container.php';

/** @var Config $config */
$config = $container->get(Config::class);
if ($config->isProduction()) {
    fwrite(STDERR, "Refusing to load demo data in production.\n");
    exit(1);
}

// The seeder is dev-only code (autoload-dev); it does not exist in the production image at all.
if (!class_exists(DemoDataSeeder::class)) {
    fwrite(STDERR, "Demo seeder not available in this build.\n");
    exit(1);
}

/** @var DemoDataSeeder $seeder */
$seeder = $container->get(DemoDataSeeder::class);
$seeder->run();

echo "Demo data loaded.\n";
