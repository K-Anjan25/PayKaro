<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Mail\EvidenceRequestMail;
use App\Mail\InvoiceSentMail;
use App\Mail\OverdueReminderMail;
use App\Models\Buyer;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

/**
 * Sending the three messages a receivable needs (BRAND_PLAN §4.1).
 *
 * All three go to the buyer, all three are computed from the invoice rather than
 * from anything typed at send time, and all three answer with a sentence rather
 * than an exception when there is nothing to send — a missing address is a normal
 * state for an MSME customer record, and "this invoice is not overdue" is not an
 * error either.
 *
 * Every action authorises *first*. The pre-checks below read the invoice (its
 * overdue days, its checklist), so a viewer who could reach them would get a
 * description of the invoice in place of a 403 — the honest answer for a read-only
 * role is the same one every other write gives.
 */
class InvoiceMailController extends Controller
{
    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorizeSending($invoice);

        $buyer = $this->recipient($invoice);

        if ($buyer === null) {
            return $this->noAddress($invoice);
        }

        Mail::to($buyer->email)->send(new InvoiceSentMail($invoice));

        return $this->sent($invoice, sprintf('Invoice %s emailed to %s.', $invoice->number, $buyer->email));
    }

    public function remind(Invoice $invoice): RedirectResponse
    {
        $this->authorizeSending($invoice);

        if ($invoice->overdueDays() < 1) {
            return $this->sent($invoice, sprintf(
                'Invoice %s is not overdue yet — it is due on %s, so no reminder was sent.',
                $invoice->number,
                $invoice->due_date?->format('d M Y') ?? 'receipt',
            ));
        }

        $buyer = $this->recipient($invoice);

        if ($buyer === null) {
            return $this->noAddress($invoice);
        }

        $mail = new OverdueReminderMail($invoice);

        Mail::to($buyer->email)->send($mail);

        return $this->sent($invoice, sprintf(
            'Reminder for %s sent to %s, quoting %s.',
            $invoice->number,
            $buyer->email,
            money($mail->totalPayable()),
        ));
    }

    public function requestEvidence(Invoice $invoice): RedirectResponse
    {
        $this->authorizeSending($invoice);

        $mail = new EvidenceRequestMail($invoice);

        if ($mail->missing()->isEmpty()) {
            return $this->sent($invoice, sprintf(
                'Every required document is already recorded against %s, so nothing was requested.',
                $invoice->number,
            ));
        }

        $buyer = $this->recipient($invoice);

        if ($buyer === null) {
            return $this->noAddress($invoice);
        }

        Mail::to($buyer->email)->send($mail);

        return $this->sent($invoice, sprintf(
            'Request for %s sent to %s, naming %s.',
            $invoice->number,
            $buyer->email,
            plural($mail->missing()->count(), 'pending document'),
        ));
    }

    /**
     * Emailing the buyer commits the business to a document they will reply to,
     * so it takes the same check as any other write to the book.
     */
    private function authorizeSending(Invoice $invoice): void
    {
        Gate::authorize('email', $invoice);
    }

    /**
     * The buyer, when there is somewhere to send to. Null means the record has no
     * address yet — the caller says so rather than failing.
     */
    private function recipient(Invoice $invoice): ?Buyer
    {
        $buyer = $invoice->buyer;

        return $buyer && filled($buyer->email) ? $buyer : null;
    }

    private function noAddress(Invoice $invoice): RedirectResponse
    {
        return $this->sent($invoice, sprintf(
            'No email address is on file for %s, so nothing was sent. Add one on the buyer record first.',
            $invoice->buyer?->name ?? 'this buyer',
        ));
    }

    private function sent(Invoice $invoice, string $message): RedirectResponse
    {
        return redirect()->route('invoices.show', $invoice)->with('status', $message);
    }
}
