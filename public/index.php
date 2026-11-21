<?php

declare(strict_types=1);

/*
 * HTTP entry point. nginx sends every request here (docker/nginx/default.conf).
 *
 * Request in:   this file → middleware stack (config/app.php) → route (config/routes.php) → controller
 * Response out: the controller returns a PSR-7 response, $app->run() writes it to the client.
 */

require __DIR__ . '/../vendor/autoload.php';

/** @var Psr\Container\ContainerInterface $container */
$container = require __DIR__ . '/../config/container.php';

/** @var Slim\App<Psr\Container\ContainerInterface> $app */
$app = (require __DIR__ . '/../config/app.php')($container);

$app->run();
