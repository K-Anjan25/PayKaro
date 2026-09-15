<?php

namespace Tests\Feature;

use App\Enums\EvidenceType;
use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Mail\EvidenceRequestMail;
use App\Mail\InvoiceSentMail;
use App\Mail\OverdueReminderMail;
use App\Models\Buyer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * The three messages a receivable needs (BRAND_PLAN §4.1).
 *
 * The coherence check the plan asks for is the first test here: the reminder a
 * buyer receives has to quote the *same interest figure* the invoice page shows.
 * A reminder that disagrees with the supplier's own screen is worse than no
 * reminder — one of the two is going to a statutory claim.
 */
final class InvoiceMailTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    private function buyerWithEmail(?string $email = 'ap@buyer.example'): Buyer
    {
        return Buyer::factory()->create(['name' => 'Metro Ceramics Ltd', 'email' => $email]);
    }

    private function overdueInvoice(User $owner, Buyer $buyer, array $attributes = []): Invoice
    {
        return Invoice::factory()->forBuyer($buyer)->overdue(20)->create($attributes + [
            'number' => 'INV-MAIL-1',
            'base_amount' => 100000,
            'tax_amount' => 18000,
            'total_amount' => 118000,
            'status' => InvoiceStatus::Accepted,
        ]);
    }

    public function test_the_reminder_quotes_the_same_interest_the_invoice_page_shows(): void
    {
        $owner = $this->workspace();
        $invoice = $this->overdueInvoice($owner, $this->buyerWithEmail());

        $this->assertGreaterThan(0, $invoice->interest(), 'the fixture must actually be accruing interest');

        // What the workspace itself renders — the app's own formatter, not a value
        // the test recomputes.
        $onPage = money($invoice->interest());
        $this->get(route('invoices.show', $invoice))->assertOk()->assertSee($onPage);

        Mail::fake();

        $this->post(route('invoices.remind', $invoice))
            ->assertRedirect(route('invoices.show', $invoice));

        Mail::assertSent(OverdueReminderMail::class, function (OverdueReminderMail $mail) use ($invoice, $onPage) {
            return $mail->hasTo('ap@buyer.example')
                && $mail->invoice->is($invoice)
                && str_contains($mail->render(), $onPage);
        });

        Mail::assertSent(OverdueReminderMail::class, function (OverdueReminderMail $mail) use ($onPage, $invoice) {
            // …and in the plain-text part too: these land on cheap Android clients,
            // and a figure that only survives the HTML alternative is a figure half
            // the recipients never see.
            $text = view('mail.invoice.overdue-text', $mail->content()->with)->render();

            return str_contains($text, $onPage) && str_contains($text, $invoice->number);
        });
    }

    public function test_the_invoice_email_carries_the_due_date_from_the_record(): void
    {
        $owner = $this->workspace();
        $invoice = $this->overdueInvoice($owner, $this->buyerWithEmail());

        Mail::fake();

        $this->post(route('invoices.send', $invoice))->assertRedirect(route('invoices.show', $invoice));

        Mail::assertSent(InvoiceSentMail::class, function (InvoiceSentMail $mail) use ($invoice) {
            $body = $mail->render();

            return $mail->hasTo('ap@buyer.example')
                && $mail->envelope()->subject === sprintf(
                    'Invoice %s from %s — %s due %s',
                    $invoice->number,
                    $invoice->business->name,
                    money($invoice->balance()),
                    $invoice->due_date->format('d M Y'),
                )
                // The due date is derived (invoice date + the MSMED window) and
                // stored; the email repeats the stored value, never a typed one.
                && str_contains($body, $invoice->due_date->format('d M Y'))
                && str_contains($body, money($invoice->balance()));
        });
    }

    public function test_the_evidence_request_names_the_rows_the_checklist_reports_missing(): void
    {
        $owner = $this->workspace();
        $invoice = $this->overdueInvoice($owner, $this->buyerWithEmail());

        foreach (EvidenceType::cases() as $type) {
            $invoice->evidences()->create(['type' => $type, 'present' => false]);
        }

        $invoice->evidences()->where('type', EvidenceType::Grn->value)->update(['present' => true]);

        Mail::fake();

        $this->post(route('invoices.request-evidence', $invoice))->assertRedirect(route('invoices.show', $invoice));

        Mail::assertSent(EvidenceRequestMail::class, function (EvidenceRequestMail $mail) {
            $missing = $mail->missing();
            $body = $mail->render();

            return $mail->hasTo('ap@buyer.example')
                && $missing->contains('Goods receipt note') === false
                && $missing->contains('Purchase order')
                && str_contains($body, 'Purchase order')
                && ! str_contains($body, 'Goods receipt note');
        });
    }

    public function test_a_buyer_without_an_address_is_told_rather_than_failing(): void
    {
        $owner = $this->workspace();
        $invoice = $this->overdueInvoice($owner, $this->buyerWithEmail(null));

        Mail::fake();

        $this->post(route('invoices.send', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'No email address is on file')
                && str_contains($status, 'Metro Ceramics Ltd'));

        Mail::assertNothingSent();
    }

    public function test_a_reminder_is_refused_when_the_invoice_is_not_overdue(): void
    {
        $owner = $this->workspace();
        $buyer = $this->buyerWithEmail();

        $invoice = Invoice::factory()->forBuyer($buyer)->create([
            'number' => 'INV-NOT-DUE',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => InvoiceStatus::Raised,
        ]);

        Mail::fake();

        $this->post(route('invoices.remind', $invoice))
            ->assertRedirect(route('invoices.show', $invoice))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'is not overdue yet'));

        Mail::assertNothingSent();
    }

    public function test_a_complete_checklist_requests_nothing(): void
    {
        $owner = $this->workspace();
        $invoice = $this->overdueInvoice($owner, $this->buyerWithEmail());

        foreach (EvidenceType::cases() as $type) {
            $invoice->evidences()->create(['type' => $type, 'present' => true]);
        }

        Mail::fake();

        $this->post(route('invoices.request-evidence', $invoice))
            ->assertSessionHas('status', fn (string $status) => str_contains($status, 'nothing was requested'));

        Mail::assertNothingSent();
    }

    public function test_a_viewer_reads_the_ledger_and_sends_nothing(): void
    {
        $owner = $this->workspace();
        $invoice = $this->overdueInvoice($owner, $this->buyerWithEmail());

        $viewer = User::factory()->for($owner->business)->create(['role' => UserRole::Viewer]);
        $this->actingAs($viewer);

        Mail::fake();

        $this->post(route('invoices.send', $invoice))->assertForbidden();
        $this->post(route('invoices.remind', $invoice))->assertForbidden();
        $this->post(route('invoices.request-evidence', $invoice))->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_another_workspace_has_no_invoice_to_email(): void
    {
        $other = $this->otherWorkspace();

        // Created by the other tenant's own member, because the tenant is resolved
        // from the authenticated user — then this test signs in as a different one.
        $invoice = $this->overdueInvoice($other, $this->buyerAs($other, ['email' => 'ap@other.example']));

        $this->workspace();

        Mail::fake();

        // The tenant scope answers 404 — the same answer as "no such invoice".
        $this->post(route('invoices.send', $invoice))->assertNotFound();

        Mail::assertNothingSent();
    }
}
