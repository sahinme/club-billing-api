<?php

declare(strict_types=1);

use ClubBilling\Http\Controller\GetInvoiceController;
use ClubBilling\Http\Controller\HealthController;
use ClubBilling\Http\Controller\LoginController;
use ClubBilling\Http\Controller\PayInvoiceController;
use Psr\Container\ContainerInterface;
use Slim\App;

/**
 * Every endpoint the API exposes.
 *
 * Authentication is deny-by-default: AuthenticationMiddleware requires a bearer token on every
 * route except the names listed in AuthenticationMiddleware::PUBLIC_ROUTES ('health', 'auth.login').
 * A route added here later is protected automatically.
 *
 * @return Closure(App<ContainerInterface>): void
 */
return static function (App $app): void {
    $app->get('/health', HealthController::class)->setName('health');
    $app->post('/v1/auth/login', LoginController::class)->setName('auth.login');

    $app->get('/v1/invoices/{invoiceId:[0-9]+}', GetInvoiceController::class)->setName('invoices.view');
    $app->post('/v1/invoices/{invoiceId:[0-9]+}/payments', PayInvoiceController::class)->setName('invoices.pay');
};
