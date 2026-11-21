<?php

declare(strict_types=1);

namespace ClubBilling\Tests\Integration;

use ClubBilling\Database\Seed\DemoDataSeeder as Demo;

final class PayInvoiceTest extends ApiTestCase
{
    public function testFullPaymentMarksInvoicePaidAndRecordsAnEvent(): void
    {
        $token = $this->loginAs(Demo::FRONT_DESK);

        $response = $this->pay($token, Demo::INVOICE_ANNA_OPEN, 4999);

        self::assertSame(201, $response->getStatusCode());
        $payment = self::json($response);
        self::assertSame('succeeded', $payment['status']);
        self::assertStringStartsWith('ch_', (string) $payment['gateway_reference']);

        $invoice = self::json($this->request('GET', '/v1/invoices/' . Demo::INVOICE_ANNA_OPEN, headers: ['Authorization' => "Bearer {$token}"]));
        self::assertSame('paid', $invoice['status']);
        self::assertSame(0, $invoice['amount_outstanding']);

        self::assertSame(1, $this->countRows('outbox_messages', "event_type = 'payment.succeeded' AND published_at IS NULL"));
    }

    public function testSameIdempotencyKeyReturnsTheOriginalPaymentAndChargesOnce(): void
    {
        $token = $this->loginAs(Demo::FRONT_DESK);

        $first = $this->pay($token, Demo::INVOICE_ANNA_LARGE, 1000, idempotencyKey: 'retry-key-0001');
        $retry = $this->pay($token, Demo::INVOICE_ANNA_LARGE, 1000, idempotencyKey: 'retry-key-0001');

        self::assertSame(201, $retry->getStatusCode());
        self::assertSame('true', $retry->getHeaderLine('Idempotent-Replayed'));
        self::assertSame(self::json($first)['id'], self::json($retry)['id']);
        self::assertSame(1, $this->countRows('payments'));
    }

    public function testSameIdempotencyKeyWithDifferentBodyIsRefused(): void
    {
        $token = $this->loginAs(Demo::FRONT_DESK);
        $this->pay($token, Demo::INVOICE_ANNA_LARGE, 1000, idempotencyKey: 'reused-key-0001');

        $response = $this->pay($token, Demo::INVOICE_ANNA_LARGE, 2000, idempotencyKey: 'reused-key-0001');

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('idempotency_key_reused', self::json($response)['code']);
    }

    public function testDeclinedCardReturns402AndLeavesInvoiceUntouched(): void
    {
        $token = $this->loginAs(Demo::FRONT_DESK);

        $response = $this->pay($token, Demo::INVOICE_BOB_OPEN, 2500, 'pm_card_declined');

        self::assertSame(402, $response->getStatusCode());
        self::assertSame('card_declined', self::json($response)['failure_reason']);
        self::assertSame(1, $this->countRows('invoices', "id = 2 AND status = 'open' AND amount_paid = 0"));
        self::assertSame(1, $this->countRows('outbox_messages', "event_type = 'payment.failed'"));
    }

    public function testGatewayTimeoutLeavesPaymentPendingAndBlocksAnotherPayment(): void
    {
        $token = $this->loginAs(Demo::FRONT_DESK);

        $timeout = $this->pay($token, Demo::INVOICE_ANNA_LARGE, 1000, 'pm_card_timeout');
        self::assertSame(202, $timeout->getStatusCode());
        self::assertSame('pending', self::json($timeout)['status']);

        // We don't know yet whether the first charge went through, so a second one must wait.
        $second = $this->pay($token, Demo::INVOICE_ANNA_LARGE, 1000);
        self::assertSame(409, $second->getStatusCode());
        self::assertSame('payment_in_progress', self::json($second)['code']);
    }

    public function testCannotPayMoreThanOutstanding(): void
    {
        $response = $this->pay($this->loginAs(Demo::FRONT_DESK), Demo::INVOICE_ANNA_OPEN, 5000);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('amount_exceeds_outstanding', self::json($response)['code']);
        self::assertSame(0, $this->countRows('payments'));
    }

    public function testCannotPayAnAlreadyPaidInvoice(): void
    {
        $response = $this->pay($this->loginAs(Demo::FRONT_DESK), Demo::INVOICE_ANNA_PAID, 100);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame('invoice_not_open', self::json($response)['code']);
    }

    public function testRoleWithoutPermissionGets403(): void
    {
        $response = $this->pay($this->loginAs(Demo::AUDITOR), Demo::INVOICE_BOB_OPEN, 100);

        self::assertSame(403, $response->getStatusCode());
    }

    public function testInvoiceOfAnotherClubLooksLikeItDoesNotExist(): void
    {
        $response = $this->pay($this->loginAs(Demo::OTHER_CLUB_FRONT_DESK), Demo::INVOICE_ANNA_OPEN, 100);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame(0, $this->countRows('payments'));
    }

    public function testMemberCanPayOwnInvoiceButNotSomeoneElses(): void
    {
        $anna = $this->loginAs(Demo::MEMBER_ANNA);

        self::assertSame(201, $this->pay($anna, Demo::INVOICE_ANNA_LARGE, 500)->getStatusCode());
        self::assertSame(404, $this->pay($anna, Demo::INVOICE_BOB_OPEN, 500)->getStatusCode());
    }

    public function testInvalidInputIsRejectedWithAllErrorsAtOnce(): void
    {
        $response = $this->request(
            'POST',
            '/v1/invoices/' . Demo::INVOICE_ANNA_OPEN . '/payments',
            ['amount' => 49.99, 'currency' => 'eur', 'payment_method' => '4111111111111111'],
            ['Authorization' => 'Bearer ' . $this->loginAs(Demo::FRONT_DESK)],
        );

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            ['Idempotency-Key', 'amount', 'currency', 'payment_method'],
            array_keys((array) self::json($response)['errors']),
        );
    }

    public function testErrorsNeverExposeInternals(): void
    {
        // Break the database on purpose: drop a table the payment flow needs.
        $this->pdo->exec('RENAME TABLE payments TO payments_hidden');
        try {
            $response = $this->pay($this->loginAs(Demo::FRONT_DESK), Demo::INVOICE_ANNA_OPEN, 100);
        } finally {
            $this->pdo->exec('RENAME TABLE payments_hidden TO payments');
        }

        self::assertSame(500, $response->getStatusCode());
        $body = (string) $response->getBody();
        self::assertStringNotContainsString('SQLSTATE', $body);
        self::assertStringNotContainsString('payments', $body);
        self::assertNotEmpty(self::json($response)['request_id']);
    }
}
