<?php

declare(strict_types=1);

namespace ClubBilling\Application\Payment;

use ClubBilling\Application\Auth\Authorizer;
use ClubBilling\Application\Conflict;
use ClubBilling\Application\ResourceNotFound;
use ClubBilling\Domain\BusinessRuleViolation;
use ClubBilling\Domain\Invoice\Invoice;
use ClubBilling\Domain\Payment\Payment;
use ClubBilling\Domain\User\Permission;
use ClubBilling\Infrastructure\Database\TransactionManager;
use ClubBilling\Infrastructure\Persistence\IdempotencyRecord;
use ClubBilling\Infrastructure\Persistence\IdempotencyRepository;
use ClubBilling\Infrastructure\Persistence\InvoiceRepository;
use ClubBilling\Infrastructure\Persistence\OutboxRepository;
use ClubBilling\Infrastructure\Persistence\PaymentRepository;
use LogicException;
use Psr\Log\LoggerInterface;

/**
 * Use case: collect a payment for an invoice.
 *
 * The flow is split around the call to the payment provider on purpose:
 *
 *   1. reserve()  short DB transaction: claim the idempotency key, lock the invoice, check the rules,
 *                 create a "pending" payment.
 *   2. charge     call the provider OUTSIDE any transaction. A slow provider must not hold
 *                 database locks, and a DB rollback cannot undo a card charge anyway.
 *   3. settle()   short DB transaction: store the outcome, update the invoice, write the event to the outbox.
 *
 * If the process dies between 2 and 3 the payment stays "pending", which is the honest state:
 * we do not know if money moved. See README "Weak spots" for the reconciliation job this needs.
 */
final readonly class PayInvoiceService
{
    public function __construct(
        private TransactionManager $transactions,
        private InvoiceRepository $invoices,
        private PaymentRepository $payments,
        private IdempotencyRepository $idempotencyKeys,
        private OutboxRepository $outbox,
        private PaymentGateway $gateway,
        private Authorizer $authorizer,
        private LoggerInterface $logger,
    ) {}

    public function handle(PayInvoiceCommand $command): PaymentResult
    {
        // Cheapest check first: is this kind of user allowed to collect payments at all?
        $this->authorizer->assertCan($command->actor, Permission::CollectPayments);

        // A retry of a request we already handled: answer with the original payment, never charge twice.
        $previous = $this->idempotencyKeys->find($command->actor->id, $command->idempotencyKey);
        if ($previous !== null) {
            return $this->replay($previous, $command);
        }

        try {
            $payment = $this->transactions->run(fn(): Payment => $this->reserve($command));
        } catch (IdempotencyKeyAlreadyClaimed) {
            // A parallel request with the same key got there first. Answer with its payment.
            $previous = $this->idempotencyKeys->find($command->actor->id, $command->idempotencyKey)
                ?? throw new LogicException('Idempotency key was claimed but cannot be found.');

            return $this->replay($previous, $command);
        }

        $charge = $this->chargeOutsideTransaction($payment, $command);
        if ($charge !== null) {
            $this->transactions->run(fn() => $this->settle($payment, $charge));
        }

        return new PaymentResult($payment, replayed: false);
    }

    /** Step 1, inside a transaction. */
    private function reserve(PayInvoiceCommand $command): Payment
    {
        $actor = $command->actor;

        $this->idempotencyKeys->claim($actor->id, $command->idempotencyKey, $command->fingerprint());

        // Locks the invoice row: a second payment for the same invoice waits here until we commit.
        $invoice = $this->invoices->findForUpdate($command->invoiceId, $actor->clubId)
            ?? throw new ResourceNotFound('Invoice');
        $this->authorizer->assertCanAccessInvoice($actor, $invoice);
        $invoice->assertCanAcceptPayment($command->amount);

        // While one charge is in flight we cannot know the outstanding amount, so we allow only one at a time.
        if ($this->payments->hasPendingForInvoice($invoice->id)) {
            throw new Conflict('payment_in_progress', 'Another payment for this invoice is still being processed.');
        }

        $payment = $this->payments->createPending($invoice, $command->amount, $actor->id);
        $this->idempotencyKeys->attachPayment($actor->id, $command->idempotencyKey, $payment->id);

        $this->logger->info('payment.reserved', [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $payment->amount->amount,
            'currency' => $payment->amount->currency,
        ]);

        return $payment;
    }

    /** Step 2. Returns null when the outcome is unknown (provider timeout). */
    private function chargeOutsideTransaction(Payment $payment, PayInvoiceCommand $command): ?ChargeResult
    {
        try {
            return $this->gateway->charge(new ChargeRequest(
                amount: $payment->amount,
                paymentMethod: $command->paymentMethod,
                // Our payment id is also the provider's idempotency key, so retrying this call never charges twice.
                idempotencyKey: 'payment-' . $payment->id,
            ));
        } catch (GatewayTimeout) {
            // We don't know if the card was charged. Leave the payment pending; never guess with money.
            $this->logger->warning('payment.gateway_timeout', [
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoiceId,
            ]);

            return null;
        }
    }

    /** Step 3, inside a transaction. */
    private function settle(Payment $payment, ChargeResult $charge): void
    {
        $invoice = $this->invoices->findForUpdate($payment->invoiceId, $payment->clubId)
            ?? throw new LogicException(sprintf('Invoice %d disappeared during payment %d.', $payment->invoiceId, $payment->id));

        if ($charge->approved) {
            $payment->markSucceeded($charge->reference);
            $invoice->applyPayment($payment->amount);
            $this->invoices->save($invoice);
        } else {
            $payment->markFailed($charge->reference, $charge->declineReason ?? 'declined');
        }
        $this->payments->save($payment);

        $eventType = $charge->approved ? 'payment.succeeded' : 'payment.failed';
        $this->outbox->record($eventType, (string) $invoice->id, $this->eventData($payment, $invoice));

        $this->logger->info($eventType, [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'invoice_status' => $invoice->status->value,
            'failure_reason' => $payment->failureReason,
        ]);
    }

    private function replay(IdempotencyRecord $record, PayInvoiceCommand $command): PaymentResult
    {
        // Same key, different body: almost certainly a client bug. Refuse instead of guessing which one they meant.
        // 422 as in the IETF "Idempotency-Key HTTP header" draft.
        if ($record->requestFingerprint !== $command->fingerprint()) {
            throw new BusinessRuleViolation('idempotency_key_reused', 'This Idempotency-Key was already used for a different request.');
        }

        $payment = ($record->paymentId === null ? null : $this->payments->find($record->paymentId))
            ?? throw new LogicException('Idempotency key has no payment attached.');

        $this->logger->info('payment.replayed', ['payment_id' => $payment->id]);

        return new PaymentResult($payment, replayed: true);
    }

    /**
     * Event payload for other services (accounting, notifications, ...).
     * Ids and amounts only. No personal data, so the topic does not become a second copy of user data.
     *
     * @return array<string, mixed>
     */
    private function eventData(Payment $payment, Invoice $invoice): array
    {
        return [
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'club_id' => $invoice->clubId,
            'amount' => $payment->amount->amount,
            'currency' => $payment->amount->currency,
            'payment_status' => $payment->status->value,
            'failure_reason' => $payment->failureReason,
            'invoice_status' => $invoice->status->value,
            'invoice_outstanding' => $invoice->outstanding()->amount,
        ];
    }
}
