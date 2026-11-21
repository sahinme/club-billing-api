<?php

declare(strict_types=1);

namespace ClubBilling\Http\Controller;

use ClubBilling\Application\Payment\PayInvoiceService;
use ClubBilling\Domain\Payment\PaymentStatus;
use ClubBilling\Http\Middleware\AuthenticationMiddleware;
use ClubBilling\Http\Request\PayInvoiceRequest;
use ClubBilling\Http\Response\JsonResponder;
use ClubBilling\Http\Response\Presenters;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * POST /v1/invoices/{invoiceId}/payments
 *
 * Thin on purpose: HTTP in, HTTP out. Every decision lives in PayInvoiceService.
 */
final readonly class PayInvoiceController
{
    public function __construct(
        private PayInvoiceService $payInvoice,
        private JsonResponder $responder,
    ) {}

    /** @param array{invoiceId: string} $args route placeholders; the route regex guarantees digits */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // 1. Who is calling (set by AuthenticationMiddleware).
        $actor = AuthenticationMiddleware::userFrom($request);

        // 2. Validate the raw input and turn it into a typed command.
        $command = PayInvoiceRequest::toCommand($request, $actor, (int) $args['invoiceId']);

        // 3. Run the use case.
        $result = $this->payInvoice->handle($command);

        // 4. The response goes out here.
        return $this->responder->json(
            Presenters::payment($result->payment),
            self::httpStatusFor($result->payment->status),
            $result->replayed ? ['Idempotent-Replayed' => 'true'] : [],
        );
    }

    private static function httpStatusFor(PaymentStatus $status): int
    {
        return match ($status) {
            PaymentStatus::Succeeded => 201, // Created: the money is collected.
            PaymentStatus::Pending => 202,   // Accepted: the provider timed out, outcome not known yet.
            PaymentStatus::Failed => 402,    // Payment Required: the card was declined. Body says why.
        };
    }
}
