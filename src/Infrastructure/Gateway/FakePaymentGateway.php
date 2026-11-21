<?php

declare(strict_types=1);

namespace ClubBilling\Infrastructure\Gateway;

use ClubBilling\Application\Payment\ChargeRequest;
use ClubBilling\Application\Payment\ChargeResult;
use ClubBilling\Application\Payment\GatewayTimeout;
use ClubBilling\Application\Payment\PaymentGateway;

/**
 * Stand-in for a real payment provider. The payment method token picks the outcome,
 * so every path can be shown in the demo and covered by tests:
 *
 *   pm_card_declined            declined, reason "card_declined"
 *   pm_card_insufficient_funds  declined, reason "insufficient_funds"
 *   pm_card_timeout             GatewayTimeout: we don't know if money moved
 *   anything else               approved
 */
final readonly class FakePaymentGateway implements PaymentGateway
{
    /** @param int $latencyMs simulated network time, so concurrent requests really overlap in the demo */
    public function __construct(private int $latencyMs = 0) {}

    public function charge(ChargeRequest $request): ChargeResult
    {
        if ($this->latencyMs > 0) {
            usleep($this->latencyMs * 1000);
        }

        $reference = 'ch_' . bin2hex(random_bytes(8));

        return match ($request->paymentMethod) {
            'pm_card_declined' => ChargeResult::declined($reference, 'card_declined'),
            'pm_card_insufficient_funds' => ChargeResult::declined($reference, 'insufficient_funds'),
            'pm_card_timeout' => throw new GatewayTimeout('Simulated provider timeout.'),
            default => ChargeResult::approved($reference),
        };
    }
}
