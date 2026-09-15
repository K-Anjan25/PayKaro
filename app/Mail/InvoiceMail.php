<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The three emails a receivable actually needs, in one place.
 *
 * BRAND_PLAN §4.1: there was no email layer at all — no `resources/views/mail/`,
 * no Mailable — in a product whose whole pitch is the buyer relationship. A
 * supplier chasing a payment over WhatsApp has no record; these are the three
 * messages that create one, and they are the *same numbers* the invoice page
 * shows, because they are computed from the same invoice:
 *
 *   - the due date comes off the record (`Receivables::dueDate()`, stored on the
 *     invoice), never a hand-typed one;
 *   - the interest is `$invoice->interest()`, so the email and the page cannot
 *     disagree — a reminder that quotes a different figure from the page is worse
 *     than no reminder;
 *   - the missing evidence is the checklist the invoice itself reports.
 *
 * Subclasses name their subject and views; everything else is shared, including
 * the plain-text alternative every message gets, because MSME accounts-payable
 * inboxes are cheap Android clients and a text/plain part is what they render.
 */
abstract class InvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    /**
     * The subject, in the buyer's terms: invoice number, amount, and the date that
     * matters to them. Built here rather than in each view so the subject and the
     * body cannot quote different numbers — and named `subjectLine` because
     * `Mailable::subject()` is the framework's own setter.
     */
    abstract public function subjectLine(): string;

    /**
     * The HTML view, and the plain-text one beside it.
     *
     * @return array{0: string, 1: string}
     */
    abstract public function views(): array;

    /**
     * Everything the views need beyond `$invoice` — a mailable's public *methods*
     * are not view variables, only its public properties are, so each derived
     * figure is handed over under a name the template can read.
     *
     * @return array<string, mixed>
     */
    abstract public function data(): array;

    public function envelope(): Envelope
    {
        $business = $this->invoice->business;

        /* The sender is the supplier, not the platform: this is their invoice, and
           a buyer replying has to reach them. The address itself comes from
           config/mail.php (the deployment's verified sender), with the owner's
           address as reply-to — a small deployment has no per-tenant sending
           domain, and pretending otherwise would make every message fail SPF. */
        $owner = $this->sender();

        return new Envelope(
            from: new Address(config('mail.from.address'), $business?->name ?? config('mail.from.name')),
            replyTo: $owner ? [new Address($owner->email, $owner->name)] : [],
            subject: $this->subjectLine(),
        );
    }

    public function content(): Content
    {
        [$html, $text] = $this->views();

        return new Content(
            view: $html,
            text: $text,
            with: [
                // `invoice` is explicit rather than relying on Laravel hoisting the
                // mailable's public properties: the templates and the tests both read
                // this array, and one of them should not be able to see less than the
                // other.
                'invoice' => $this->invoice,
                'businessName' => $this->businessName(),
                'sender' => $this->sender(),
            ] + $this->data(),
        );
    }

    /**
     * The supplier, for the signature block and the bank details a buyer needs to
     * actually pay.
     */
    public function businessName(): string
    {
        return $this->invoice->business?->name ?? config('app.name');
    }

    /**
     * Who signed it — the business owner, which is who a buyer expects to hear from.
     */
    public function sender(): ?User
    {
        return $this->invoice->business?->owner;
    }
}
