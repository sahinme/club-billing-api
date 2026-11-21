<?php

declare(strict_types=1);

namespace ClubBilling\Application\Payment;

/**
 * The payment service provider (PSP), seen from our side.
 * An interface because it is an external system: we swap it for a fake in tests and locally,
 * and could switch provider without touching the use case.
 */
interface PaymentGateway
{
    /**
     * @throws GatewayTimeout when the provider did not answer in time. The charge may or may not have happened.
     */
    public function charge(ChargeRequest $request): ChargeResult;
}
