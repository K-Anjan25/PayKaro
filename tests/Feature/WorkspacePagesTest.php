<?php

namespace Tests\Feature;

use App\Enums\AlertType;
use App\Enums\InvoiceStatus;
use App\Enums\TredsOnboarding;
use App\Models\Alert;
use App\Models\Business;
use App\Models\Buyer;
use App\Models\Invoice;
use App\Models\User;
use App\Tenancy\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * The workspace pages a member lands on, and the filters they are wired to.
 */
final class WorkspacePagesTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_the_dashboard_reports_the_book_as_a_whole(): void
    {
        $user = $this->workspace();
        $buyer = Buyer::factory()->create(['treds_onboarded' => TredsOnboarding::Yes]);

        $open = Invoice::factory()->forBuyer($buyer)->overdue(10)->create([
            'number' => 'INV-BOARD-1',
            'base_amount' => 100000,
            'tax_amount' => 18000,
            'total_amount' => 118000,
            'status' => InvoiceStatus::Accepted,
        ]);

        Invoice::factory()->forBuyer($buyer)->create([
            'number' => 'INV-BOARD-2',
            'base_amount' => 5000,
            'tax_amount' => 0,
            'total_amount' => 5000,
            'status' => InvoiceStatus::Settled,
        ]);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('INV-BOARD-1')
            ->assertSee(money($open->balance()))
            ->assertSee('Ageing Summary')
            ->assertSee('Receivables Status Pipeline');
    }

    public function test_an_empty_workspace_is_told_so_instead_of_showing_zeroes_everywhere(): void
    {
        $this->workspace();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('No invoices yet')
            ->assertSee(money(0));
    }

    public function test_unread_attention_items_are_listed_and_dismissing_them_clears_the_list(): void
    {
        $user = $this->workspace();
        $business = $user->business;

        $business->alerts()->create([
            'type' => AlertType::Dispute->value,
            'message' => 'Two invoices are past due.',
            'read_at' => null,
        ]);

        $this->get(route('dashboard'))->assertOk()->assertSee('Two invoices are past due.');

        $this->post(route('alerts.read'))->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Two invoices are past due.');

        $this->assertSame(1, Alert::query()->withoutGlobalScope(TenantScope::class)->count());
    }

    public function test_the_invoice_list_filters_by_pipeline_state(): void
    {
        $user = $this->workspace();
        $buyer = Buyer::factory()->create();

        Invoice::factory()->forBuyer($buyer)->create(['number' => 'INV-RAISED-1']);
        Invoice::factory()->forBuyer($buyer)->create(['number' => 'INV-ACCEPTED-1', 'status' => InvoiceStatus::Accepted]);

        $this->get(route('invoices.index', ['status' => 'accepted']))
            ->assertOk()
            ->assertSee('INV-ACCEPTED-1')
            ->assertDontSee('INV-RAISED-1');

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('INV-RAISED-1')
            ->assertSee('INV-ACCEPTED-1');
    }

    public function test_the_header_search_matches_a_number_or_a_buyer_name(): void
    {
        $user = $this->workspace();
        $metro = Buyer::factory()->create(['name' => 'Metro Ceramics Ltd']);
        $other = Buyer::factory()->create(['name' => 'Zenith Steel Works']);

        Invoice::factory()->forBuyer($metro)->create(['number' => 'INV-METRO-7']);
        Invoice::factory()->forBuyer($other)->create(['number' => 'INV-ZENITH-8']);

        $this->get(route('invoices.index', ['q' => 'METRO-7']))
            ->assertOk()
            ->assertSee('INV-METRO-7')
            ->assertDontSee('INV-ZENITH-8');

        $this->get(route('invoices.index', ['q' => 'zenith steel']))
            ->assertOk()
            ->assertSee('INV-ZENITH-8')
            ->assertDontSee('INV-METRO-7');

        $this->get(route('invoices.index', ['q' => 'nothing-matches-this']))
            ->assertOk()
            ->assertSee('No invoices yet')
            ->assertDontSee('INV-METRO-7');

        // A term made only of LIKE wildcards sanitises to nothing, which is the
        // same as not searching: no rows leak, and nobody is told "no results".
        $this->get(route('invoices.index', ['q' => '%_%']))
            ->assertOk()
            ->assertSee('INV-METRO-7')
            ->assertSee('INV-ZENITH-8')
            ->assertDontSee('Nothing matched');
    }

    public function test_search_and_filter_combine_and_the_pager_keeps_them(): void
    {
        $user = $this->workspace();
        $buyer = Buyer::factory()->create(['name' => 'Kaveri Tools']);

        Invoice::factory()->forBuyer($buyer)->create(['number' => 'INV-KAV-1', 'status' => InvoiceStatus::Accepted]);
        Invoice::factory()->forBuyer($buyer)->create(['number' => 'INV-KAV-2', 'status' => InvoiceStatus::Raised]);

        $response = $this->get(route('invoices.index', ['q' => 'KAV', 'status' => 'accepted']));

        $response->assertOk()->assertSee('INV-KAV-1')->assertDontSee('INV-KAV-2');

        // Only one result, so no pager — but the tab link must still carry q.
        $response->assertSee('q=KAV');
    }

    public function test_the_finance_queue_separates_ready_from_blocked(): void
    {
        $user = $this->workspace();
        $readyBuyer = Buyer::factory()->create(['name' => 'Ready Traders', 'treds_onboarded' => TredsOnboarding::Yes]);
        $slowBuyer = Buyer::factory()->create(['name' => 'Slow Traders', 'treds_onboarded' => TredsOnboarding::No]);

        Invoice::factory()->forBuyer($readyBuyer)->withEvidence()->create([
            'number' => 'INV-READY-1',
            'status' => InvoiceStatus::Accepted,
        ]);
        Invoice::factory()->forBuyer($slowBuyer)->create([
            'number' => 'INV-BLOCKED-1',
            'status' => InvoiceStatus::Accepted,
        ]);

        $this->get(route('treds'))
            ->assertOk()
            ->assertSee('INV-READY-1')
            ->assertSee('Finance it')
            ->assertSee('INV-BLOCKED-1')
            ->assertSee('Buyer is not on TReDS yet');
    }

    public function test_the_finance_queue_is_empty_for_a_fresh_workspace(): void
    {
        $this->workspace();

        $this->get(route('treds'))->assertOk()->assertSee('Nothing in the queue');
    }

    public function test_reports_group_the_outstanding_by_buyer(): void
    {
        $user = $this->workspace();
        $metro = Buyer::factory()->create(['name' => 'Metro Ceramics Ltd']);
        $zenith = Buyer::factory()->create(['name' => 'Zenith Steel Works']);

        Invoice::factory()->forBuyer($metro)->create(['base_amount' => 100000, 'tax_amount' => 0, 'total_amount' => 100000]);
        Invoice::factory()->forBuyer($metro)->create(['base_amount' => 50000, 'tax_amount' => 0, 'total_amount' => 50000]);
        Invoice::factory()->forBuyer($zenith)->create(['base_amount' => 10000, 'tax_amount' => 0, 'total_amount' => 10000]);

        $this->get(route('reports'))
            ->assertOk()
            ->assertSee('Outstanding by buyer')
            ->assertSee('Metro Ceramics Ltd')
            ->assertSee(money(150000))
            ->assertSee('Zenith Steel Works')
            ->assertSee(money(10000));
    }

    public function test_the_settings_page_lets_the_owner_edit_identity_and_nobody_else(): void
    {
        $owner = $this->workspace();

        $this->put(route('settings.update'), [
            'name' => 'Shree Precision Works Pvt Ltd',
            'gstin' => '36aaacs1234f1z5',
            'pan' => 'aaacs1234f',
            'udyam_no' => 'udyam-ts-12-0000123',
            'bank_name' => 'HDFC Bank',
            'bank_acc_no' => '50100012345678',
            'bank_ifsc' => 'hfdc0001234',
            'treds_registered' => 1,
        ])->assertRedirect(route('settings.edit'));

        $business = $owner->business->refresh();

        $this->assertSame('Shree Precision Works Pvt Ltd', $business->name);
        $this->assertSame('36AACS1234F1Z5', $business->gstin, 'identity fields are upper-cased for the claim forms');
        $this->assertSame('AAACS1234F', $business->pan);
        $this->assertSame('UDYAM-TS-12-0000123', $business->udyam_no);
        $this->assertSame('HDFC0001234', $business->bank_ifsc);
        $this->assertTrue((bool) $business->treds_registered);

        $accountant = User::factory()->accountant()->for($business)->create();
        $this->actingAs($accountant);

        $this->put(route('settings.update'), ['name' => 'Renamed by accountant', 'treds_registered' => 0])
            ->assertForbidden();

        $this->assertSame('Shree Precision Works Pvt Ltd', $business->refresh()->name);
    }

    public function test_a_malformed_gstin_or_ifsc_is_named_as_the_problem(): void
    {
        $owner = $this->workspace();
        $untouched = $owner->business->name;

        $this->from(route('settings.edit'))->put(route('settings.update'), [
            'name' => 'Honest Ltd',
            'gstin' => 'not-a-gstin',
            'bank_ifsc' => 'HDFC0',
            'treds_registered' => 0,
        ])
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHasErrors(['gstin', 'bank_ifsc']);

        $business = Business::query()->sole();

        $this->assertSame($untouched, $business->name, 'a rejected form must not half-save');
        $this->assertNotSame('not-a-gstin', $business->gstin);
    }

    public function test_the_buyers_page_lists_the_book_and_links_to_add_one(): void
    {
        $this->workspace();
        Buyer::factory()->create(['name' => 'Anand Pipes', 'gstin' => '36AABCU9603R1ZX']);

        $this->get(route('buyers.index'))
            ->assertOk()
            ->assertSee('Anand Pipes')
            ->assertSee('36AABCU9603R1ZX')
            ->assertSee('+ Add buyer');

        $this->get(route('buyers.create'))->assertOk()->assertSee('Add a buyer');
    }

    public function test_adding_a_buyer_stores_their_treds_answer_and_says_so(): void
    {
        $this->workspace();

        $this->post(route('buyers.store'), [
            'name' => 'Bharat Fittings Ltd',
            'gstin' => '',
            'type' => 'private',
            'treds_onboarded' => 'no',
        ])->assertRedirect(route('buyers.index'))->assertSessionHas('status');

        $buyer = Buyer::query()->sole();

        $this->assertSame(TredsOnboarding::No, $buyer->treds_onboarded);
        $this->assertStringContainsString('Bharat Fittings Ltd', session('status'));
        $this->assertNull($buyer->gstin);
    }

    public function test_a_buyer_needs_a_name(): void
    {
        $this->workspace();

        $this->post(route('buyers.store'), ['name' => '', 'type' => 'private', 'treds_onboarded' => 'unknown'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, Buyer::query()->count());
    }

    public function test_a_viewer_cannot_add_a_buyer(): void
    {
        $owner = $this->workspace();
        $viewer = User::factory()->viewer()->for($owner->business)->create();

        $this->actingAs($viewer);

        $this->post(route('buyers.store'), ['name' => 'Sneaky Ltd', 'type' => 'private', 'treds_onboarded' => 'unknown'])
            ->assertForbidden();

        $this->get(route('buyers.index'))->assertOk()->assertSee('No buyers yet');
    }

    public function test_draft_is_offered_as_a_filter_and_lets_you_stay_silent(): void
    {
        $this->workspace();

        $this->get(route('invoices.index', ['status' => 'draft']))
            ->assertOk()
            ->assertSee('Nothing in this state');
    }

    public function test_a_settled_invoice_keeps_its_history_but_stops_accruing(): void
    {
        $user = $this->workspace();
        $buyer = Buyer::factory()->create();

        $invoice = Invoice::factory()->forBuyer($buyer)->overdue(90)->create([
            'status' => InvoiceStatus::Accepted,
            'base_amount' => 100000,
            'tax_amount' => 18000,
            'total_amount' => 118000,
        ]);

        $this->assertGreaterThan(0, $invoice->interest());
        $this->assertSame(90, $invoice->overdueDays());

        $this->patch(route('invoices.status', $invoice), ['status' => 'settled']);

        $invoice->refresh();

        $this->assertSame(0, $invoice->interest());
        $this->assertSame(0.0, $invoice->balance());
        $this->assertSame(0, $invoice->overdueDays());
        $this->assertNotNull($invoice->paid_date);
    }
}
