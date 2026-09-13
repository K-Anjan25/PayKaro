<?php

namespace App\Services;

use App\Enums\AlertType;
use App\Enums\DisputeForum;
use App\Enums\EvidenceType;
use App\Enums\InvoiceStatus;
use App\Models\Dispute;
use App\Models\Financing;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

/**
 * Every write to an invoice, in one place.
 *
 * This is the layer that knows the *rules*: raising an invoice derives its due
 * date and GST, seeds the evidence checklist, flags a buyer who is not on TReDS,
 * a payment caps at the outstanding balance and settles the invoice when it
 * clears, a claim computes its statutory deadline from the due date.
 *
 * Controllers stay thin (validate, call, redirect) and the invariants cannot be
 * bypassed by a second call site — which is exactly what the single-action
 * `PayKaro` class was for before this port.
 */
class InvoiceWorkflow
{
    public function __construct(protected Receivables $receivables) {}

    /**
     * Raise an invoice for the current business.
     *
     * The due date, tax and total are derived, never trusted from the request:
     * the 45-day window and the interest clock both start from the invoice date,
     * so a client-sent due date would be a way to edit the statute.
     *
     * @param  array{number: string, buyer_id: int, invoice_date: string, base_amount: float|string, tax_amount?: float|string|null, notes?: string|null}  $data
     */
    public function create(array $data): Invoice
    {
        $base = round((float) $data['base_amount'], 2);
        $tax = $this->receivables->taxOn($base, $this->optionalAmount($data['tax_amount'] ?? null));

        return DB::transaction(function () use ($data, $base, $tax) {
            $invoice = Invoice::create([
                'buyer_id' => (int) $data['buyer_id'],
                'number' => $data['number'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $this->receivables->dueDate($data['invoice_date']),
                'base_amount' => $base,
                'tax_amount' => $tax,
                'total_amount' => $this->receivables->total($base, $tax),
                'status' => InvoiceStatus::Raised,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->seedEvidenceChecklist($invoice);
            $this->raiseBuyerOnboardingAlert($invoice);

            return $invoice;
        });
    }

    /**
     * @param  array{number?: string, buyer_id?: int, invoice_date?: string, base_amount?: float|string, tax_amount?: float|string|null, notes?: string|null}  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoiceDate = $data['invoice_date'] ?? $invoice->invoice_date?->toDateString() ?? $invoice->invoice_date;
        $base = array_key_exists('base_amount', $data)
            ? round((float) $data['base_amount'], 2)
            : (float) $invoice->base_amount;
        $tax = $this->receivables->taxOn($base, $this->optionalAmount($data['tax_amount'] ?? null));

        $invoice->fill([
            'number' => $data['number'] ?? $invoice->number,
            'buyer_id' => isset($data['buyer_id']) ? (int) $data['buyer_id'] : $invoice->buyer_id,
            'invoice_date' => $invoiceDate,
            'due_date' => $this->receivables->dueDate($invoiceDate),
            'base_amount' => $base,
            'tax_amount' => $tax,
            'total_amount' => $this->receivables->total($base, $tax),
            'notes' => $data['notes'] ?? $invoice->notes,
        ])->save();

        return $invoice;
    }

    /**
     * Move an invoice along the pipeline.
     *
     * Acceptance and settlement dates are stamped the first time they apply and
     * then kept: "when did the buyer accept" is evidence, and re-flagging the
     * invoice must not rewrite it.
     */
    public function setStatus(Invoice $invoice, InvoiceStatus $status): Invoice
    {
        $today = now()->toDateString();

        $attributes = ['status' => $status];

        if (in_array($status, [InvoiceStatus::Accepted, InvoiceStatus::Financed, InvoiceStatus::Settled], true)
            && $invoice->approval_date === null) {
            $attributes['approval_date'] = $today;
        }

        if ($status === InvoiceStatus::Settled) {
            $attributes['paid_date'] = $invoice->paid_date?->toDateString() ?? $today;
        }

        $invoice->forceFill($attributes)->save();

        return $invoice->refresh();
    }

    /**
     * Tick or untick one line of the evidence checklist.
     */
    public function setEvidence(Invoice $invoice, EvidenceType $type, bool $present): void
    {
        $invoice->evidences()->updateOrCreate(
            ['type' => $type->value],
            ['present' => $present],
        );
    }

    /**
     * Record money received.
     *
     * The amount is capped at what is still outstanding, and a payment that
     * clears the balance settles the invoice — the receivable and the ledger can
     * never disagree.
     *
     * @param  array{amount: float|string, paid_on?: string|null, method?: string|null, reference?: string|null}  $data
     */
    public function recordPayment(Invoice $invoice, array $data): ?Payment
    {
        $balance = $invoice->balance();

        if ($balance <= 0) {
            return null;
        }

        $amount = min(max(0.0, round((float) $data['amount'], 2)), $balance);

        if ($amount <= 0) {
            return null;
        }

        $payment = $invoice->payments()->create([
            'amount' => $amount,
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
            'method' => $data['method'] ?? null,
            'reference' => $data['reference'] ?? null,
        ]);

        if ($amount >= $balance - 0.001) {
            $this->setStatus($invoice->refresh(), InvoiceStatus::Settled);
        }

        return $payment;
    }

    /**
     * Record a TReDS discounting disbursal and mark the invoice financed.
     *
     * @param  array{financier?: string|null, discount_rate?: float|string|null, amount_disbursed?: float|string|null, disbursed_on?: string|null}  $data
     */
    public function recordFinancing(Invoice $invoice, array $data): Financing
    {
        $amount = $this->optionalAmount($data['amount_disbursed'] ?? null) ?? (float) $invoice->total_amount;

        $financing = $invoice->financings()->create([
            'financier' => filled($data['financier'] ?? null) ? $data['financier'] : 'Bank',
            'discount_rate' => max(0.0, (float) ($data['discount_rate'] ?? 1.5)),
            'amount_disbursed' => round($amount, 2),
            'disbursed_on' => $data['disbursed_on'] ?? now()->toDateString(),
            'status' => 'disbursed',
        ]);

        $this->setStatus($invoice->refresh(), InvoiceStatus::Financed);

        return $financing;
    }

    /**
     * Open a claim and record the deadline that comes with it.
     *
     * @param  array{forum: string}  $data
     */
    public function startDispute(Invoice $invoice, array $data): Dispute
    {
        $forum = $data['forum'] instanceof DisputeForum
            ? $data['forum']
            : DisputeForum::from((string) $data['forum']);

        $deadline = $invoice->due_date
            ? $forum->deadlineFrom($invoice->due_date)
            : null;

        return DB::transaction(function () use ($invoice, $forum, $deadline) {
            $dispute = $invoice->disputes()->create([
                'forum' => $forum,
                'stage' => 'filing',
                'filed_on' => now()->toDateString(),
                'deadline_on' => $deadline,
            ]);

            $this->setStatus($invoice->refresh(), InvoiceStatus::Disputed);

            $invoice->business->alerts()->create([
                'invoice_id' => $invoice->id,
                'type' => AlertType::Dispute->value,
                'message' => sprintf(
                    'Dispute filed. Deadline: %s — gather the evidence packet.',
                    $deadline ?? '—',
                ),
            ]);

            return $dispute;
        });
    }

    /* ------------------------------------------------------------------ *
     * Internals
     * ------------------------------------------------------------------ */

    /**
     * Every invoice starts with the full checklist unticked, so a gap is a
     * recorded fact rather than a missing row.
     */
    protected function seedEvidenceChecklist(Invoice $invoice): void
    {
        foreach (EvidenceType::cases() as $type) {
            $invoice->evidences()->create([
                'type' => $type->value,
                'present' => false,
            ]);
        }
    }

    /**
     * The single most common reason an MSME cannot discount: the buyer is not on
     * the exchange. Say so at the moment it becomes visible.
     */
    protected function raiseBuyerOnboardingAlert(Invoice $invoice): void
    {
        if ($invoice->buyerOnboarding()->isOnboarded()) {
            return;
        }

        $invoice->business->alerts()->create([
            'invoice_id' => $invoice->id,
            'type' => AlertType::Treds->value,
            'message' => 'Buyer is not TReDS-onboarded — confirm before the invoice churns.',
        ]);
    }

    /**
     * An empty string from an optional number field is "not provided", not zero.
     */
    protected function optionalAmount(float|int|string|null $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $amount = (float) $value;

        return $amount >= 0 ? $amount : null;
    }
}
