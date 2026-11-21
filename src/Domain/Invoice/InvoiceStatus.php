<?php

declare(strict_types=1);

namespace ClubBilling\Domain\Invoice;

enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';
}
