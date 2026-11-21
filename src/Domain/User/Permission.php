<?php

declare(strict_types=1);

namespace ClubBilling\Domain\User;

enum Permission: string
{
    case ViewInvoices = 'invoices.view';
    case CollectPayments = 'payments.collect';
}
