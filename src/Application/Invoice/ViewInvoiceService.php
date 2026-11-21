<?php

declare(strict_types=1);

namespace ClubBilling\Application\Invoice;

use ClubBilling\Application\Auth\Authorizer;
use ClubBilling\Application\ResourceNotFound;
use ClubBilling\Domain\Invoice\Invoice;
use ClubBilling\Domain\User\Permission;
use ClubBilling\Domain\User\User;
use ClubBilling\Infrastructure\Persistence\InvoiceRepository;

final readonly class ViewInvoiceService
{
    public function __construct(
        private InvoiceRepository $invoices,
        private Authorizer $authorizer,
    ) {}

    public function handle(User $actor, int $invoiceId): Invoice
    {
        $this->authorizer->assertCan($actor, Permission::ViewInvoices);

        $invoice = $this->invoices->find($invoiceId, $actor->clubId) ?? throw new ResourceNotFound('Invoice');
        $this->authorizer->assertCanAccessInvoice($actor, $invoice);

        return $invoice;
    }
}
