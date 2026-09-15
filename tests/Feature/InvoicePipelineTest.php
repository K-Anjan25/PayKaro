<?php

namespace Tests\Feature;

use App\Enums\EvidenceType;
use App\Enums\InvoiceStatus;
use App\Enums\TredsOnboarding;
use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\Buyer;
use App\Models\Dispute;
use App\Models\Financing;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * The pipeline itself, driven through the same endpoints the UI uses.
 *
 * These are the rules a supplier's claim depends on, so they are asserted at the
 * HTTP edge (what the form can do) and at the row level (what actually got
 * stored), including the dates the app refuses to let anyone rewrite.
 */
final class InvoicePipelineTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    /**
     * A buyer for the workspace under test, finance-ready unless the test says
     * otherwise (defaults lose to the attributes the test passes).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function buyer(array $attributes = []): Buyer
    {
        return Buyer::factory()->create($attributes + [
            'name' => 'Metro Ceramics Ltd',
            'treds_onboarded' => TredsOnboarding::Yes,
        ]);
    }

    private function member(User $owner, UserRole $role): User
    {
        return User::factory()->for($owner->business)->create(['role' => $role]);
    }

    public function test_raising_an_invoice_derives_tax_total_and_the_due_date(): void
    {
        $user = $this->workspace();
        $buyer = $this->buyer();

        $this->post(route('invoices.store'), [
            'number' => 'INV-2026-0001',
            'buyer_id' => $buyer->id,
            'invoice_date' => '2026-04-21',
            'base_amount' => 100000,
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::query()->sole();

        $this->assertSame(InvoiceStatus::Raised, $invoice->status);
        $this->assertSame('2026-06-05', $invoice->due_date->toDateString());
        $this->assertSame(18000.0, (float) $invoice->tax_amount);
        $this->assertSame(118000.0, (float) $invoice->total_amount);
        $this->assertSame($buyer->id, $invoice->buyer_id);
        $this->assertSame($user->business_id, $invoice->business_id);
        $this->assertSame('raised', $invoice->status->value);
    }

    public function test_an_explicit_tax_figure_wins_and_zero_means_no_gst(): void
    {
        $this->workspace();
        $buyer = $this->buyer();

        $this->post(route('invoices.store'), [
            'number' => 'INV-TAX-0',
            'buyer_id' => $buyer->id,
            'invoice_date' => '2026-04-21',
            'base_amount' => 100000,
            'tax_amount' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) Invoice::query()->sole()->tax_amount);
    }

    public function test_a_blank_tax_field_is_worked_out_rather_than_treated_as_zero(): void
    {
        $this->workspace();
        $buyer = $this->buyer();

        $this->post(route('invoices.store'), [
            'number' => 'INV-TAX-BLANK',
            'buyer_id' => $buyer->id,
            'invoice_date' => '2026-04-21',
            'base_amount' => 50000,
            'tax_amount' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame(9000.0, (float) Invoice::query()->sole()->tax_amount);
    }

    public function test_a_new_invoice_starts_with_the_evidence_checklist_seeded(): void
    {
        $this->workspace();
        $buyer = $this->buyer();

        $this->post(route('invoices.store'), [
            'number' => 'INV-EVI-1',
            'buyer_id' => $buyer->id,
            'invoice_date' => now()->toDateString(),
            'base_amount' => 10000,
        ]);

        $invoice = Invoice::query()->sole();

        $this->assertCount(count(EvidenceType::cases()), $invoice->evidences);
        $this->assertSame(0, $invoice->presentRequiredEvidence());
        $this->assertSame(4, $invoice->requiredEvidenceCount());
        $this->assertFalse($invoice->isFinanceReady());
    }

    public function test_a_buyer_not_on_treds_becomes_an_attention_item(): void
    {
        $this->workspace();
        $buyer = $this->buyer(['treds_onboarded' => TredsOnboarding::No]);

        $this->post(route('invoices.store'), [
            'number' => 'INV-ALERT-1',
            'buyer_id' => $buyer->id,
            'invoice_date' => now()->toDateString(),
            'base_amount' => 10000,
        ]);

        $alert = Alert::query()->sole();

        $this->assertStringContainsString('TReDS', $alert->message);
        $this->assertSame('INV-ALERT-1', $alert->invoice->number);
    }

    public function test_a_suspect_invoice_date_or_amount_is_refused_with_words(): void
    {
        $this->workspace();
        $buyer = $this->buyer();

        $this->from(route('invoices.create'))->post(route('invoices.store'), [
            'number' => 'INV-BAD-1',
            'buyer_id' => $buyer->id,
            'invoice_date' => '21/04/2026',
            'base_amount' => 1000,
        ])->assertSessionHasErrors('invoice_date');

        $this->assertStringContainsString(
            'must be a real date',
            session('errors')->first('invoice_date'),
        );

        $this->post(route('invoices.store'), [
            'number' => 'INV-BAD-2',
            'buyer_id' => $buyer->id,
            'invoice_date' => now()->toDateString(),
            'base_amount' => 0,
        ])->assertSessionHasErrors('base_amount');

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_a_duplicate_number_in_the_same_business_is_a_field_error(): void
    {
        $user = $this->workspace();
        $buyer = $this->buyer();

        $this->post(route('invoices.store'), [
            'number' => 'INV-DUP-1',
            'buyer_id' => $buyer->id,
            'invoice_date' => now()->toDateString(),
            'base_amount' => 1000,
        ]);

        $this->post(route('invoices.store'), [
            'number' => 'INV-DUP-1',
            'buyer_id' => $buyer->id,
            'invoice_date' => now()->toDateString(),
            'base_amount' => 2000,
        ])->assertSessionHasErrors('number');

        $this->assertSame(1, Invoice::query()->count());
        $this->assertStringContainsString('already have an invoice', session('errors')->first('number'));
    }

    public function test_moving_the_invoice_acceptance_date_is_stamped_once(): void
    {
        $user = $this->workspace();
        $buyer = $this->buyer();
        $invoice = Invoice::factory()->forBuyer($buyer)->create();

        $this->patch(route('invoices.status', $invoice), ['status' => 'accepted'])
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Accepted, $invoice->status);
        $this->assertSame(now()->toDateString(), $invoice->approval_date->toDateString());

        $approvalDate = $invoice->approval_date;

        // Flagging a dispute afterwards must not rewrite when the buyer accepted.
        $this->patch(route('invoices.status', $invoice), ['status' => 'disputed']);
        $this->patch(route('invoices.status', $invoice), ['status' => 'accepted']);

        $this->assertEquals($approvalDate, $invoice->refresh()->approval_date);
        $this->assertNull($invoice->paid_date);
    }

    public function test_an_unknown_status_is_rejected(): void
    {
        $user = $this->workspace();
        $invoice = Invoice::factory()->forBuyer($this->buyer())->create();

        $this->patch(route('invoices.status', $invoice), ['status' => 'paid'])->assertSessionHasErrors('status');

        $this->assertSame(InvoiceStatus::Raised, $invoice->refresh()->status);
    }

    public function test_ticking_evidence_moves_the_readiness_score(): void
    {
        $user = $this->workspace();
        $buyer = $this->buyer(['treds_onboarded' => TredsOnboarding::Yes]);
        $invoice = Invoice::factory()->forBuyer($buyer)->create(['status' => InvoiceStatus::Accepted]);

        $before = $invoice->readiness();

        $this->put(route('invoices.evidence', $invoice), ['type' => 'po', 'present' => 1])
            ->assertRedirect(route('invoices.show', $invoice));

        $this->assertTrue($invoice->refresh()->evidences->firstWhere('type', EvidenceType::Po)->present);
        $this->assertGreaterThan($before, $invoice->readiness());

        // Unticking is just as legal — the checklist is a statement of fact.
        $this->put(route('invoices.evidence', $invoice), ['type' => 'po', 'present' => 0]);

        $this->assertSame($before, $invoice->refresh()->readiness());
    }

    public function test_a_non_required_document_can_be_recorded_too(): void
    {
        $this->workspace();
        $invoice = Invoice::factory()->forBuyer($this->buyer())->create();

        $this->put(route('invoices.evidence', $invoice), ['type' => 'contract', 'present' => 1]);

        $this->assertTrue($invoice->refresh()->evidences->firstWhere('type', EvidenceType::Contract)->present);

        // An optional attachment cannot lift the required-evidence count.
        $this->assertSame(0, $invoice->presentRequiredEvidence());
    }

    public function test_an_evidence_type_that_does_not_exist_is_refused(): void
    {
        $this->workspace();
        $invoice = Invoice::factory()->forBuyer($this->buyer())->create();

        $this->put(route('invoices.evidence', $invoice), ['type' => 'pan-card', 'present' => 1])
            ->assertSessionHasErrors('type');
    }

    public function test_a_part_payment_lowers_the_balance_without_settling(): void
    {
        $this->workspace();
        $invoice = Invoice::factory()->forBuyer($this->buyer())->create([
            'base_amount' => 100000,
            'tax_amount' => 18000,
            'total_amount' => 118000,
            'status' => InvoiceStatus::Accepted,
        ]);

        $this->post(route('invoices.payments.store', $invoice), [
            'amount' => 18000,
            'paid_on' => now()->toDateString(),
            'method' => 'NEFT',
            'reference' => 'UTR-123',
        ])->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();

        $this->assertSame(100000.0, $invoice->balance());
        $this->assertSame(InvoiceStatus::Accepted, $invoice->status);

        $payment = Payment::query()->sole();

        $this->assertSame(18000.0, (float) $payment->amount);
        $this->assertSame('UTR-123', $payment->reference);
    }

    public function test_an_overpayment_is_clamped_and_clears_the_invoice(): void
    {
        $this->workspace();
        $invoice = Invoice::factory()->forBuyer($this->buyer())->create([
            'base_amount' => 10000,
            'tax_amount' => 0,
            'total_amount' => 10000,
            'status' => InvoiceStatus::Accepted,
        ]);

        $this->post(route('invoices.payments.store', $invoice), ['amount' => 999999]);

        $invoice->refresh();

        $this->assertSame(10000.0, (float) Payment::query()->sole()->amount, 'the receipt records what was owed');
        $this->assertSame(0.0, $invoice->balance());
        $this->assertSame(InvoiceStatus::Settled, $invoice->status);
        $this->assertSame(now()->toDateString(), $invoice->paid_date->toDateString());
        $this->assertSame(0, $invoice->overdueDays());
    }

    public function test_settled_invoices_refuse_further_payments(): void
    {
        $this->workspace();
        $invoice = Invoice::factory()->forBuyer($this->buyer())->create(['status' => InvoiceStatus::Settled]);

        $this->post(route('invoices.payments.store', $invoice), ['amount' => 500]);

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_recording_a_disbursal_finances_the_invoice(): void
    {
        $this->workspace();
        $invoice = Invoice::factory()->forBuyer($this->buyer())->create([
            'base_amount' => 200000,
            'tax_amount' => 36000,
            'total_amount' => 236000,
            'status' => InvoiceStatus::Accepted,
        ]);

        $this->post(route('invoices.financings.store', $invoice), [
            'financier' => 'ICICI Bank',
            'discount_rate' => 1.2,
            'amount_disbursed' => 233000,
        ])->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $financing = Financing::query()->sole();

        $this->assertSame(InvoiceStatus::Financed, $invoice->status);
        $this->assertSame('ICICI Bank', $financing->financier);
        $this->assertSame(1.2, (float) $financing->discount_rate);
        $this->assertSame(233000.0, (float) $financing->amount_disbursed);
        $this->assertSame(now()->toDateString(), $financing->disbursed_on->toDateString());
    }

    public function test_a_dispute_opens_with_the_statutory_deadline_and_an_alert(): void
    {
        $this->workspace();
        $invoice = Invoice::factory()->forBuyer($this->buyer())->create([
            'invoice_date' => '2026-01-15',
            'due_date' => '2026-03-01',
            'status' => InvoiceStatus::Accepted,
        ]);

        // Filing a claim lands on the evidence packet, not back on the invoice:
        // the deadline and the documents are the next thing the supplier needs,
        // and the controller says so (see DisputeController::store).
        $this->post(route('invoices.disputes.store', $invoice), ['forum' => 'msefc'])
            ->assertRedirect(route('invoices.claim', $invoice));

        $invoice->refresh();
        $dispute = Dispute::query()->sole();

        $this->assertSame(InvoiceStatus::Disputed, $invoice->status);
        $this->assertSame('filing', $dispute->stage);
        $this->assertSame('2026-04-15', $dispute->deadline_on->toDateString());
        $this->assertStringContainsString('Deadline', Alert::query()->sole()->message);

        // An invoice in a forum is off the financing board.
        $this->assertFalse($invoice->canBeFinanced());
    }

    public function test_mediation_has_no_statutory_deadline(): void
    {
        $this->workspace();
        $invoice = Invoice::factory()->forBuyer($this->buyer())->create();

        $this->post(route('invoices.disputes.store', $invoice), ['forum' => 'mediation']);

        $this->assertNull(Dispute::query()->sole()->deadline_on);
    }

    public function test_the_claim_packet_names_every_gap(): void
    {
        $this->workspace();
        $buyer = $this->buyer(['gstin' => null]);
        $invoice = Invoice::factory()->forBuyer($buyer)->create([
            'number' => 'INV-CLAIM-1',
            'invoice_date' => '2026-01-15',
            'due_date' => '2026-03-01',
            'base_amount' => 100000,
            'tax_amount' => 18000,
            'total_amount' => 118000,
            'status' => InvoiceStatus::Accepted,
        ]);

        // The packet tabulates one row per *evidence row that exists*, so it can
        // only name a gap the fixture actually left open. Tick the checklist
        // (required items missing, everything the packet reads present) before
        // asserting both halves of the label.
        foreach (EvidenceType::cases() as $type) {
            $invoice->evidences()->create(['type' => $type, 'present' => false]);
        }

        $this->put(route('invoices.evidence', $invoice), ['type' => 'po', 'present' => 1]);

        $this->get(route('invoices.claim', $invoice))
            ->assertOk()
            ->assertSee('INV-CLAIM-1')
            ->assertSee('MISSING')
            ->assertSee('Attachment present')
            ->assertSee('Interest due (3× bank rate 6.5%)');
    }

    public function test_the_printed_packet_is_cited_by_gstin_and_invoice_number(): void
    {
        $owner = $this->workspace();
        // Uppercased by the settings form, which is the only place identity is
        // edited; the footer quotes whatever the business record holds.
        $owner->business->update(['name' => 'Shree Precision Works Pvt Ltd', 'gstin' => '36AAACS1234F1Z5']);

        $invoice = $this->invoice($owner, $this->buyer(), ['number' => 'INV-PRINT-1']);

        // A filing is cited by its GSTIN and its invoice number, and a packet can
        // run past one sheet — so the print footer has to carry both, under the
        // class the print stylesheet keeps out of the screen layout and repeats
        // on every page (see the print section of bridge/overflow-sim.mjs).
        $this->get(route('invoices.claim', $invoice))
            ->assertOk()
            ->assertSee('claim-print-foot', false)
            ->assertSee('Shree Precision Works Pvt Ltd · GSTIN 36AAACS1234F1Z5')
            ->assertSee('Claim packet · Invoice INV-PRINT-1');
    }

    public function test_a_viewer_reads_the_book_and_changes_nothing(): void
    {
        $owner = $this->workspace(UserRole::Owner);
        $buyer = $this->buyer();
        $invoice = Invoice::factory()->forBuyer($buyer)->create();

        $viewer = $this->member($owner, UserRole::Viewer);
        $this->actingAs($viewer);

        $this->get(route('invoices.show', $invoice))->assertOk();
        $this->get(route('invoices.index'))->assertOk();

        $this->patch(route('invoices.status', $invoice), ['status' => 'settled'])->assertForbidden();
        $this->put(route('invoices.evidence', $invoice), ['type' => 'po', 'present' => 1])->assertForbidden();
        $this->post(route('invoices.payments.store', $invoice), ['amount' => 100])->assertForbidden();
        $this->post(route('invoices.store'), [
            'number' => 'INV-VIEWER-1',
            'buyer_id' => $buyer->id,
            'invoice_date' => now()->toDateString(),
            'base_amount' => 1000,
        ])->assertForbidden();

        $this->assertSame(InvoiceStatus::Raised, $invoice->refresh()->status);
    }

    public function test_editing_an_invoice_rederives_the_terms(): void
    {
        $this->workspace();
        $invoice = Invoice::factory()->forBuyer($this->buyer())->create([
            'number' => 'INV-EDIT-1',
            'invoice_date' => '2026-04-21',
            'due_date' => '2026-06-05',
            'base_amount' => 100000,
            'tax_amount' => 18000,
            'total_amount' => 118000,
        ]);

        $this->put(route('invoices.update', $invoice), [
            'number' => 'INV-EDIT-1',
            'buyer_id' => $invoice->buyer_id,
            'invoice_date' => '2026-05-01',
            'base_amount' => 200000,
        ])->assertSessionHasNoErrors();

        $invoice->refresh();

        $this->assertSame('2026-06-15', $invoice->due_date->toDateString());
        $this->assertSame(36000.0, (float) $invoice->tax_amount);
        $this->assertSame(236000.0, (float) $invoice->total_amount);
    }
}
