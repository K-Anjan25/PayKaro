<?php

namespace App\Services;

use App\Models\Invoice;

/**
 * The printable evidence packet for an MSEFC / mediation / arbitration claim.
 *
 * A claim is only as good as the dated documents behind it, so the packet states
 * each fact the forum will ask about *and* calls out what is missing — the point
 * being to find the gap before filing, not at the hearing.
 */
class ClaimPacket
{
    public function __construct(protected Receivables $receivables) {}

    /**
     * @return list<array{label: string, value: string, missing: bool}>
     */
    public function rows(Invoice $invoice): array
    {
        $rows = [
            $this->row('Invoice', $invoice->number.' dated '.$invoice->invoice_date?->format('d M Y')),
            $this->row('Buyer', sprintf(
                '%s (%s)',
                $invoice->buyer?->name ?? '—',
                $invoice->buyer?->type->label() ?? 'unknown',
            )),
            $this->row('GSTIN', $invoice->buyer?->gstin ?: 'Not on record'),
            $this->row('Invoice amount', money($invoice->total_amount)),
            $this->row('Due date', $invoice->due_date?->format('d M Y') ?? '—'),
            $this->row('Days overdue', (string) $invoice->overdueDays()),
            $this->row($this->interestLabel(), money($invoice->interest())),
        ];

        foreach ($invoice->evidences()->orderBy('id')->get() as $evidence) {
            $rows[] = [
                'label' => $evidence->type->label(),
                'value' => $evidence->present
                    ? 'Attachment present'
                    : 'MISSING — attach before filing',
                'missing' => ! $evidence->present,
            ];
        }

        $latestDispute = $invoice->disputes()->latest('filed_on')->latest('id')->first();

        $rows[] = $this->row('File before', $latestDispute?->deadline_on?->format('d M Y') ?? '—');

        return $rows;
    }

    /**
     * How many required documents are still missing, for the "not ready" banner.
     */
    public function missingCount(Invoice $invoice): int
    {
        return $invoice->requiredEvidenceCount() - $invoice->presentRequiredEvidence();
    }

    /**
     * Interest line heading, phrased from the live configuration rather than a
     * hard-coded "3×", so a rate change never leaves a stale claim form. It names
     * the method as well as the rate, because the method is what Section 16
     * prescribes and what the schedule below the line works out.
     */
    public function interestLabel(): string
    {
        return sprintf(
            'Interest due (compound, monthly rests at %d× bank rate %s%%)',
            $this->receivables->interestMultiplier,
            rtrim(rtrim(number_format($this->receivables->bankRate, 2, '.', ''), '0'), '.'),
        );
    }

    /**
     * @return array{label: string, value: string, missing: bool}
     */
    private function row(string $label, string $value): array
    {
        return ['label' => $label, 'value' => $value, 'missing' => false];
    }
}
