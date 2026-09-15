<?php

namespace App\Mail;

use Carbon\CarbonInterface;

/**
 * Day 1 / 15 / 30 past due, quoting the interest the statute has already accrued.
 *
 * The number in this email is `$invoice->interest()` — the same call the invoice
 * page and the claim packet make — so the reminder a buyer receives and the
 * figure the supplier's own screen shows cannot drift apart. That coherence is
 * the acceptance test in BRAND_PLAN §4.1.
 */
class OverdueReminderMail extends InvoiceMail
{
    public function subjectLine(): string
    {
        $days = $this->invoice->overdueDays();

        return sprintf(
            '%s: invoice %s is %d day%s overdue — %s now payable',
            $this->businessName(),
            $this->invoice->number,
            $days,
            $days === 1 ? '' : 's',
            money($this->totalPayable()),
        );
    }

    public function views(): array
    {
        return ['mail.invoice.overdue', 'mail.invoice.overdue-text'];
    }

    public function data(): array
    {
        return [
            'totalPayable' => $this->totalPayable(),
            'escalation' => $this->escalation(),
            'asOf' => $this->asOf(),
        ];
    }

    /**
     * Balance plus the interest accrued so far — what actually has to be paid
     * today, not the face value the invoice was raised at.
     */
    public function totalPayable(): float
    {
        return round($this->invoice->balance() + $this->invoice->interest(), 2);
    }

    /**
     * The band this reminder belongs to. Not a scheduling system — the app has no
     * scheduler — but the three points MSMED practice actually uses, so the copy
     * can escalate and the sender can see which one they are sending.
     */
    public function escalation(): string
    {
        return match (true) {
            $this->invoice->overdueDays() >= 30 => 'statutory',
            $this->invoice->overdueDays() >= 15 => 'firm',
            default => 'courtesy',
        };
    }

    /**
     * When the reminder was composed — pinned in one place so the view and any
     * caller agree.
     */
    public function asOf(): CarbonInterface
    {
        return now();
    }
}
