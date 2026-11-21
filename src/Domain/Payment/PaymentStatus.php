<?php

declare(strict_types=1);

namespace ClubBilling\Domain\Payment;

enum PaymentStatus: string
{
    /** Created, provider not asked yet, or the provider did not answer (timeout). Outcome unknown. */
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
