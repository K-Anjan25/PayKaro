<?php

namespace Tests\Feature;

use App\Enums\EvidenceType;
use App\Enums\InvoiceStatus;
use App\Enums\TredsOnboarding;
use App\Enums\UserRole;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * The first-run checklist (WIREFRAME_AUDIT §6, `WIREFRAMES.md`).
 *
 * Both documents record the same gap — "sign-up lands straight on the dashboard" —
 * and the design package drew a feature tour. This is the version of it that fits a
 * server-rendered app: a card that reads the workspace's own state, states the next
 * step, and leaves once there is nothing left to say.
 *
 * So the assertions are about honesty rather than layout: the card must not claim a
 * step is done when it is not, must not appear when everything is done, and must not
 * offer a member buttons they are not allowed to press.
 */
final class OnboardingTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_an_empty_workspace_is_told_where_to_start(): void
    {
        $this->workspace();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Four steps to a finance-ready book')
            ->assertSee('0 of 4 done')
            ->assertSee('Add a buyer')
            ->assertSee('Raise an invoice')
            ->assertSee('Complete the evidence trail')
            ->assertSee("Confirm each buyer's TReDS status")
            ->assertSee(route('buyers.create'), false)
            ->assertSee(route('invoices.create'), false)
            ->assertSee(route('buyers.index'), false);
    }

    public function test_each_step_ticks_when_the_workspace_actually_does_it(): void
    {
        $owner = $this->workspace();

        $buyer = $this->buyerAs($owner);
        $this->invoice($owner, $buyer, ['number' => 'INV-FIRST-1', 'status' => InvoiceStatus::Raised]);

        // Buyer and invoice are done; the evidence trail is not, so the checklist is
        // still on the page and says two of four.
        $this->get(route('dashboard'))->assertOk()->assertSee('2 of 4 done');

        // Ticking the required documents completes the third step.
        $invoice = Invoice::query()->sole();

        foreach (EvidenceType::requiredValues() as $type) {
            $invoice->evidences()->updateOrCreate(['type' => $type], ['present' => true]);
        }

        $this->get(route('dashboard'))->assertOk()->assertSee('3 of 4 done');
    }

    public function test_the_card_leaves_when_there_is_nothing_left_to_say(): void
    {
        $owner = $this->workspace();
        // A confirmed onboarding status is the fourth and last step, so this buyer
        // has to answer it for the card to have nothing left to say.
        $buyer = $this->buyerAs($owner, ['treds_onboarded' => TredsOnboarding::Yes]);
        $invoice = $this->invoice($owner, $buyer, ['number' => 'INV-DONE-1', 'status' => InvoiceStatus::Accepted]);

        foreach (EvidenceType::requiredValues() as $type) {
            $invoice->evidences()->updateOrCreate(['type' => $type], ['present' => true]);
        }

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Four steps to a finance-ready book')
            ->assertDontSee('Getting started');
    }

    public function test_a_viewer_is_not_shown_a_checklist_of_things_they_cannot_do(): void
    {
        // Every step is a write. A read-only member gets the book without the
        // to-do list — and without six buttons that would 403.
        $owner = $this->workspace();

        $viewer = User::factory()->for($owner->business)->create(['role' => UserRole::Viewer]);
        $this->actingAs($viewer);

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Four steps to a finance-ready book')
            ->assertDontSee(route('buyers.create'), false);
    }

    public function test_the_checklist_describes_the_configured_product(): void
    {
        // The copy states the due window and the readiness threshold. Both are
        // config, so a deployment that changes them gets a checklist that matches.
        config(['paykaro.msme_due_days' => 30, 'paykaro.finance_ready_score' => 70]);

        $this->workspace();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('30-day statutory window')
            ->assertSee('getting an invoice to 70');
    }

    public function test_the_steps_point_at_screens_that_exist(): void
    {
        // A checklist that links to nothing is the orphan-screen problem with a
        // progress bar on it.
        $this->workspace();

        $body = $this->get(route('dashboard'))->assertOk()->getContent();

        foreach (['buyers.create', 'invoices.create', 'buyers.index'] as $name) {
            $this->assertStringContainsString(route($name), $body, "{$name} is not linked from the checklist");
        }
    }
}
