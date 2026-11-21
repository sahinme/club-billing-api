<?php

declare(strict_types=1);

namespace ClubBilling\Http\Controller;

use ClubBilling\Application\Invoice\ViewInvoiceService;
use ClubBilling\Http\Middleware\AuthenticationMiddleware;
use ClubBilling\Http\Response\JsonResponder;
use ClubBilling\Http\Response\Presenters;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** GET /v1/invoices/{invoiceId} */
final readonly class GetInvoiceController
{
    public function __construct(
        private ViewInvoiceService $viewInvoice,
        private JsonResponder $responder,
    ) {}

    /** @param array{invoiceId: string} $args */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $invoice = $this->viewInvoice->handle(AuthenticationMiddleware::userFrom($request), (int) $args['invoiceId']);

        return $this->responder->json(Presenters::invoice($invoice));
    }
}
