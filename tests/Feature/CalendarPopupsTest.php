<?php

namespace Tests\Feature;

use App\Enums\TredsOnboarding;
use App\Enums\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * The calendar popups that every date field in the workspace gets from
 * `partials.datepicker`.
 *
 * Two things are pinned here, because both were easy to get wrong when the
 * widget was attached from a <head> include: the popup node must exist exactly
 * once per page (the script is idempotent by a global flag, not by luck), and
 * each field's `max` must agree with the server-side date rule — a picker that
 * offers a date the FormRequest will reject is worse than no picker.
 */
final class CalendarPopupsTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_the_invoice_form_gets_a_calendar_and_caps_at_today(): void
    {
        $owner = $this->workspace(UserRole::Owner);

        // A buyer has to exist or the form never renders: with an empty book the
        // page shows its "Add a buyer first" state instead, and every assertion
        // below would be made against a empty-state page that has no date field.
        $this->buyerAs($owner);

        $html = $this->get(route('invoices.create'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="pkg-cal"'), 'exactly one popup node per page');
        $this->assertStringContainsString('__pkgDatepicker', $html);
        $this->assertStringContainsString('Open calendar', $html, 'the field needs a reachable trigger, not only a click target');

        // The field is a native date input with today as its ceiling. Assert the
        // *tag*, not an attribute order: the markup sets `type` before `name`, and
        // a substring assertion that pins the order fails on a harmless shuffle.
        $this->assertMatchesRegularExpression('/<input[^>]*name="invoice_date"[^>]*>/', $html);
        preg_match('/<input[^>]*name="invoice_date"[^>]*>/', $html, $field);
        $this->assertStringContainsString('type="date"', $field[0]);
        $this->assertStringContainsString('max="'.now()->toDateString().'"', $field[0]);
    }

    public function test_payment_and_financing_dates_are_pickable_on_the_invoice_page(): void
    {
        $owner = $this->workspace(UserRole::Owner);
        $buyer = $this->buyerAs($owner, ['treds_onboarded' => TredsOnboarding::Yes]);
        $invoice = $this->invoice($owner, $buyer);

        $html = $this->get(route('invoices.show', $invoice))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="pkg-cal"'));
        $this->assertStringContainsString('id="payment_paid_on" name="paid_on" type="date"', $html);
        // The disbursal date used to be recorded only as "today, silently" by the
        // FormRequest; the field now exists so a past-dated financing can be booked.
        $this->assertStringContainsString('id="financing_disbursed_on" name="disbursed_on" type="date"', $html);
    }

    public function test_the_paid_on_cap_matches_the_validation_rule(): void
    {
        $owner = $this->workspace(UserRole::Owner);
        $buyer = $this->buyerAs($owner, ['treds_onboarded' => TredsOnboarding::Yes]);
        $invoice = $this->invoice($owner, $buyer);

        $html = $this->get(route('invoices.show', $invoice))->assertOk()->getContent();

        // StorePaymentRequest says `before_or_equal:today`, so the widget must not
        // offer tomorrow — and must still refuse it if someone edits the DOM.
        $this->assertStringContainsString('max="'.now()->toDateString().'"', $html);
        $this->assertStringNotContainsString('max="'.now()->addDay()->toDateString().'"', $html);

        $this->post(route('invoices.payments.store', $invoice), [
            'amount' => 1000,
            'paid_on' => now()->addDay()->toDateString(),
        ])->assertSessionHasErrors('paid_on');
    }

    public function test_marketing_and_auth_pages_also_carry_the_widget_once(): void
    {
        foreach (['/', '/login', '/signup'] as $uri) {
            $html = $this->get($uri)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'id="pkg-cal"'), $uri.' should include the popup exactly once');
        }
    }
}
