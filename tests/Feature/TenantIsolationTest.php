<?php

namespace Tests\Feature;

use App\Enums\TredsOnboarding;
use App\Models\Alert;
use App\Models\Buyer;
use App\Models\Invoice;
use App\Models\User;
use App\Tenancy\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * Tenant isolation, from the outside.
 *
 * The spec's rule is that another business's data must read as *absent*, not as
 * forbidden: a 404 gives away nothing about whether the id exists. Everything
 * here is a URL an attacker could actually type.
 */
final class TenantIsolationTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    private function invoiceOf(User $owner): Invoice
    {
        $buyer = $this->buyerAs($owner, ['name' => 'Metro Ceramics Ltd']);

        return Invoice::factory()->forBuyer($buyer)->create(['number' => 'INV-SECRET-1']);
    }

    public function test_a_foreign_invoice_reads_as_absent_rather_than_forbidden(): void
    {
        $other = $this->otherWorkspace();
        $invoice = $this->invoiceOf($other);

        $this->workspace();

        foreach ([
            route('invoices.show', $invoice),
            route('invoices.edit', $invoice),
            route('invoices.claim', $invoice),
        ] as $url) {
            $this->get($url)->assertNotFound();
        }

        $this->put(route('invoices.update', $invoice), ['number' => 'x'])->assertNotFound();
    }

    public function test_another_businesss_invoice_cannot_be_written_to(): void
    {
        $other = $this->otherWorkspace();
        $invoice = $this->invoiceOf($other);

        $this->workspace();

        $this->patch(route('invoices.status', $invoice), ['status' => 'settled'])->assertNotFound();
        $this->put(route('invoices.evidence', $invoice), ['type' => 'po', 'present' => 1])->assertNotFound();
        $this->post(route('invoices.payments.store', $invoice), ['amount' => 500])->assertNotFound();
        $this->post(route('invoices.disputes.store', $invoice), ['forum' => 'msefc'])->assertNotFound();

        $this->assertSame('raised', $invoice->refresh()->status->value);
    }

    public function test_the_invoice_list_never_shows_another_workspace(): void
    {
        $other = $this->otherWorkspace();
        $hidden = $this->invoiceOf($other);

        $me = $this->workspace();
        $mine = $this->invoiceOf($me);

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee($mine->number)
            ->assertDontSee($hidden->number);
    }

    public function test_search_cannot_be_used_to_read_another_workspace(): void
    {
        $other = $this->otherWorkspace();
        $this->invoiceOf($other);

        $this->workspace();

        // The shared number is searched for explicitly: it belongs to the other
        // tenant, so the result list must be empty.
        $this->get(route('invoices.index', ['q' => 'INV-SECRET-1']))
            ->assertOk()
            ->assertSee('Nothing matched');
    }

    public function test_the_dashboard_only_counts_this_business(): void
    {
        $other = $this->otherWorkspace();
        $hidden = $this->invoiceOf($other);

        $me = $this->workspace();
        $mine = $this->invoiceOf($me);

        $combined = money($mine->total_amount + $hidden->total_amount);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee($mine->number)
            ->assertDontSee($hidden->number)
            // The dashboard's outstanding figure must be mine alone: had the
            // other tenant's invoice been summed in, this number would show.
            ->assertDontSee($combined);
    }

    public function test_a_buyer_of_another_business_cannot_be_attached_to_an_invoice(): void
    {
        $other = $this->otherWorkspace();
        $foreignBuyer = $this->buyerAs($other, ['name' => 'Poaching Ltd']);

        $this->workspace();

        $this->post(route('invoices.store'), [
            'number' => 'INV-STOLEN-1',
            'buyer_id' => $foreignBuyer->id,
            'invoice_date' => now()->toDateString(),
            'base_amount' => 50000,
        ])->assertSessionHasErrors('buyer_id');

        $this->assertStringContainsString(
            'your own buyers',
            session('errors')->first('buyer_id'),
        );

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_the_buyer_book_is_scoped(): void
    {
        $other = $this->otherWorkspace();
        $this->buyerAs($other, ['name' => 'Their Supplier']);

        $me = $this->workspace();
        $this->buyerAs($me, ['name' => 'My Customer']);

        $this->get(route('buyers.index'))
            ->assertOk()
            ->assertSee('My Customer')
            ->assertDontSee('Their Supplier');

        $this->assertSame(1, Buyer::query()->count());
    }

    public function test_the_finance_queue_and_reports_are_scoped(): void
    {
        $other = $this->otherWorkspace();
        $otherBuyer = $this->buyerAs($other, ['name' => 'Their Buyer', 'treds_onboarded' => TredsOnboarding::Yes]);
        Invoice::factory()->forBuyer($otherBuyer)->overdue(60)->create(['number' => 'INV-THEIRS-9']);

        $me = $this->workspace();
        $myBuyer = $this->buyerAs($me, ['name' => 'My Buyer', 'treds_onboarded' => TredsOnboarding::Yes]);
        Invoice::factory()->forBuyer($myBuyer)->overdue(60)->create(['number' => 'INV-MINE-9']);

        $this->get(route('treds'))->assertOk()->assertSee('INV-MINE-9')->assertDontSee('INV-THEIRS-9');
        $this->get(route('reports'))->assertOk()->assertSee('My Buyer')->assertDontSee('Their Buyer');
    }

    public function test_invoice_numbers_only_have_to_be_unique_inside_a_business(): void
    {
        $other = $this->otherWorkspace();
        $this->invoiceOf($other);

        $me = $this->workspace();
        $buyer = $this->buyerAs($me, ['name' => 'Same Number Co']);

        $this->post(route('invoices.store'), [
            'number' => 'INV-SECRET-1',
            'buyer_id' => $buyer->id,
            'invoice_date' => now()->toDateString(),
            'base_amount' => 25000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, Invoice::query()->count());
    }

    public function test_alerts_are_scoped_and_dismissal_touches_only_this_business(): void
    {
        $other = $this->otherWorkspace();
        $this->invoiceOf($other);

        $me = $this->workspace();
        $this->buyerAs($me, ['name' => 'Not Onboarded Ltd', 'treds_onboarded' => TredsOnboarding::No]);

        Alert::query()->delete();

        $foreignAlerts = Alert::query()->count();

        $this->invoiceOf($me);

        $this->assertSame(1, Alert::query()->count() - $foreignAlerts);

        $this->post(route('alerts.read'))->assertRedirect(route('dashboard'));

        $this->assertSame(0, Alert::query()->unread()->count());

        // Dismissing "all" means all of *this* business's alerts: the other
        // workspace keeps its unread copy.
        $this->assertSame(
            1,
            Alert::query()->withoutGlobalScope(TenantScope::class)->where('business_id', $other->business_id)->unread()->count(),
        );
    }

    public function test_a_user_cannot_see_a_workspace_they_do_not_belong_to(): void
    {
        $other = $this->otherWorkspace();
        $invoice = $this->invoiceOf($other);

        $stranger = $this->workspace();

        $this->assertFalse($stranger->can('view', $invoice));
        $this->get(route('invoices.show', $invoice))->assertNotFound();
    }
}
