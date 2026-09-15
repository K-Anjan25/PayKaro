<?php

namespace App\Mail;

/**
 * The invoice itself, to the buyer — the first of the three messages in
 * BRAND_PLAN §4.1, and the one that starts the clock the rest of the product
 * counts against.
 */
class InvoiceSentMail extends InvoiceMail
{
    public function subjectLine(): string
    {
        return sprintf(
            'Invoice %s from %s — %s due %s',
            $this->invoice->number,
            $this->businessName(),
            money($this->invoice->balance()),
            $this->invoice->due_date?->format('d M Y') ?? 'on receipt',
        );
    }

    public function views(): array
    {
        return ['mail.invoice.sent', 'mail.invoice.sent-text'];
    }

    public function data(): array
    {
        return [];
    }
}
