<?php

namespace Tests\Feature;

use App\Enums\BuyerType;
use App\Enums\InvoiceStatus;
use App\Enums\TredsOnboarding;
use App\Enums\UserRole;
use App\Models\Buyer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * The buyer's own page (WIREFRAME_AUDIT §6).
 *
 * The design package drew it twice — desktop and mobile — and neither existed: the
 * buyers list showed an outstanding figure with no way to find out what it was made
 * of. So these assertions are about *answers*, not layout: the page has to account
 * for the figure the list shows, and it has to say what the buyer's TReDS status
 * means for the money.
 */
final class BuyerDetailTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    private function book(?callable $configure = null): array
    {
        $owner = $this->workspace();

        $buyer = $this->buyerAs($owner, [
            'name' => 'Bharat Heavy Electricals Ltd',
            'gstin' => '36AABCB1234C1Z3',
            'type' => BuyerType::Cpse,
            'treds_onboarded' => TredsOnboarding::No,
            'email' => 'ap@bhel.example',
        ]);

        if ($configure) {
            $configure($owner, $buyer);
        }

        return [$owner, $buyer];
    }

    public function test_the_row_on_the_list_and_the_page_it_opens_agree(): void
    {
        [$owner, $buyer] = $this->book(function ($owner, $buyer) {
            $this->invoice($owner, $buyer, [
                'number' => 'INV-BUYER-1',
                'base_amount' => 100000,
                'tax_amount' => 18000,
                'total_amount' => 118000,
                'status' => InvoiceStatus::Accepted,
                'invoice_date' => now()->subDays(60)->toDateString(),
                'due_date' => now()->subDays(15)->toDateString(),
            ]);
        });

        $invoice = Invoice::query()->sole();

        $list = $this->get(route('buyers.index'))->assertOk();
        $list->assertSee(route('buyers.show', $buyer), false);

        $this->get(route('buyers.show', $buyer))
            ->assertOk()
            ->assertSee('Bharat Heavy Electricals Ltd')
            ->assertSee('36AABCB1234C1Z3')
            ->assertSee('ap@bhel.example')
            ->assertSee('INV-BUYER-1')
            // The three numbers a supplier opens a buyer record to find, computed
            // from the invoice rather than typed.
            ->assertSee(money($invoice->balance()))
            ->assertSee(money($invoice->interest()))
            ->assertSee((string) $invoice->overdueDays())
            // …and what the row's TReDS status means for the money.
            ->assertSee('not onboarded on TReDS');
    }

    public function test_a_cpse_buyer_gets_the_mandate_the_conversation_actually_needs(): void
    {
        [, $buyer] = $this->book(fn ($owner, $buyer) => $this->invoice($owner, $buyer, [
            'number' => 'INV-CPSE-1',
            'status' => InvoiceStatus::Raised,
        ]));

        // The 2026 amendment mandates exchange use for CPSE buyers, so "wait for
        // them" is the wrong advice and the page says which question to ask.
        $this->get(route('buyers.show', $buyer))
            ->assertOk()
            ->assertSee('mandates exchange use for CPSE buyers')
            ->assertSee('which exchange they onboard through');
    }

    public function test_an_unanswered_onboarding_question_is_not_reported_as_a_refusal(): void
    {
        // Unknown and No are different facts, and the product treats them
        // differently everywhere else (the readability weights score them 0 and 5).
        $owner = $this->workspace();
        $buyer = $this->buyerAs($owner, [
            'name' => 'Hydrofit Engineering LLP',
            'treds_onboarded' => TredsOnboarding::Unknown,
        ]);
        $this->invoice($owner, $buyer, ['number' => 'INV-UNKNOWN-1', 'status' => InvoiceStatus::Raised]);

        $this->get(route('buyers.show', $buyer))
            ->assertOk()
            ->assertSee('still an open question')
            ->assertSee('Unanswered');
    }

    public function test_an_onboarded_buyer_gets_no_lecture(): void
    {
        $owner = $this->workspace();
        $buyer = $this->buyerAs($owner, [
            'name' => 'Delhi Metro Rail Corp',
            'type' => BuyerType::Psu,
            'treds_onboarded' => TredsOnboarding::Yes,
        ]);
        $this->invoice($owner, $buyer, ['number' => 'INV-ONBOARDED-1', 'status' => InvoiceStatus::Accepted]);

        $this->get(route('buyers.show', $buyer))
            ->assertOk()
            ->assertSee('can be discounted once the evidence trail is complete')
            ->assertDontSee('not onboarded on TReDS')
            ->assertDontSee('still an open question');
    }

    public function test_a_buyer_with_no_invoices_says_so_and_offers_the_next_step(): void
    {
        [, $buyer] = $this->book();

        $this->get(route('buyers.show', $buyer))
            ->assertOk()
            ->assertSee('Nothing raised against Bharat Heavy Electricals Ltd yet')
            ->assertSee(route('invoices.create'), false)
            ->assertSee(money(0));
    }

    public function test_a_settled_invoice_is_listed_but_not_counted_as_outstanding(): void
    {
        [$owner, $buyer] = $this->book(function ($owner, $buyer) {
            $this->invoice($owner, $buyer, [
                'number' => 'INV-PAID-1',
                'base_amount' => 50000,
                'tax_amount' => 9000,
                'total_amount' => 59000,
                'status' => InvoiceStatus::Settled,
                'invoice_date' => now()->subDays(90)->toDateString(),
                'due_date' => now()->subDays(45)->toDateString(),
            ]);
        });

        $this->get(route('buyers.show', $buyer))
            ->assertOk()
            // The invoice is history, so it stays on the page…
            ->assertSee('INV-PAID-1')
            // …but a settled invoice is not owed, so it contributes nothing.
            ->assertSee('Outstanding')
            ->assertSee(money(0));
    }

    public function test_a_disputed_invoice_is_still_owed_and_stays_in_the_outstanding_figure(): void
    {
        // `InvoiceStatus::isClosed()` counts Disputed as closed; the buyers list does
        // not, and neither may this page — a dispute is a delay, not a payment.
        [$owner, $buyer] = $this->book(function ($owner, $buyer) {
            $this->invoice($owner, $buyer, [
                'number' => 'INV-DISPUTED-1',
                'base_amount' => 200000,
                'tax_amount' => 36000,
                'total_amount' => 236000,
                'status' => InvoiceStatus::Disputed,
                'invoice_date' => now()->subDays(100)->toDateString(),
                'due_date' => now()->subDays(55)->toDateString(),
            ]);
        });

        $invoice = Invoice::query()->sole();

        $this->get(route('buyers.show', $buyer))
            ->assertOk()
            ->assertSee(money($invoice->balance()));

        $this->assertGreaterThan(0, $invoice->interest());
    }

    public function test_a_viewer_can_read_a_buyer(): void
    {
        [$owner, $buyer] = $this->book(fn ($owner, $buyer) => $this->invoice($owner, $buyer, [
            'number' => 'INV-VIEW-1',
            'status' => InvoiceStatus::Raised,
        ]));

        $viewer = User::factory()->for($owner->business)->create(['role' => UserRole::Viewer]);
        $this->actingAs($viewer);

        // Reading the customer book is not a write. Creating is still refused.
        $this->get(route('buyers.show', $buyer))->assertOk();
        $this->get(route('buyers.create'))->assertForbidden();
    }

    public function test_another_workspaces_buyer_is_not_found(): void
    {
        // The tenant scope answers 404, the same answer as "no such buyer" — a
        // supplier editing the id must not learn that the record exists.
        $other = $this->otherWorkspace();
        $theirs = $this->buyerAs($other, ['name' => 'Their Buyer']);

        $this->workspace();

        $this->get(route('buyers.show', $theirs))->assertNotFound();
    }
}
