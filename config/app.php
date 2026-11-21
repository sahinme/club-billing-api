<?php

declare(strict_types=1);

use ClubBilling\Http\Middleware\AuthenticationMiddleware;
use ClubBilling\Http\Middleware\ErrorHandlerMiddleware;
use ClubBilling\Http\Middleware\RequestIdMiddleware;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;

/**
 * Builds the HTTP application. Used by public/index.php and by the API tests.
 *
 * @return Closure(ContainerInterface): App<ContainerInterface>
 */
return static function (ContainerInterface $container): App {
    $app = AppFactory::createFromContainer($container);

    // Slim runs middleware last-added-first. So for every request the order is:
    //   RequestId → ErrorHandler → Routing → Authentication → controller
    $app->add(AuthenticationMiddleware::class);
    $app->addRoutingMiddleware();
    $app->add(ErrorHandlerMiddleware::class);
    $app->add(RequestIdMiddleware::class);

    (require __DIR__ . '/routes.php')($app);

    return $app;
};
