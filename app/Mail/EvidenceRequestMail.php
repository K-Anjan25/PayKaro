<?php

namespace App\Mail;

use Illuminate\Support\Collection;

/**
 * "We're missing the GRN for INV-2026-007."
 *
 * The third message in BRAND_PLAN §4.1, and the one that makes the evidence
 * checklist worth keeping: a missing document is a request to a buyer, not a
 * checkbox on an internal screen. The list comes from the invoice's own checklist,
 * so the email names exactly the rows the workspace shows as missing.
 */
class EvidenceRequestMail extends InvoiceMail
{
    public function subjectLine(): string
    {
        return sprintf(
            'Documents pending for invoice %s — %s',
            $this->invoice->number,
            $this->businessName(),
        );
    }

    public function views(): array
    {
        return ['mail.invoice.evidence', 'mail.invoice.evidence-text'];
    }

    public function data(): array
    {
        return ['missing' => $this->missing()];
    }

    /**
     * The evidence rows the invoice itself reports as missing, in the checklist's
     * own order.
     *
     * @return Collection<int, string>
     */
    public function missing(): Collection
    {
        return $this->invoice->checklist()
            ->get()
            ->reject(fn ($evidence) => $evidence->present)
            ->map(fn ($evidence) => $evidence->type->label())
            ->values();
    }
}
