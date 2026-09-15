<?php

namespace Tests\Unit;

use App\Enums\AgeingBucket;
use App\Enums\InvoiceStatus;
use App\Enums\TredsOnboarding;
use App\Enums\TredsStatus;
use App\Services\Receivables;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The money rules of the app, tested as arithmetic rather than through HTTP:
 * every one of these numbers ends up in a claim packet, so a drift of a paisa
 * or a day has to fail loudly here.
 */
final class ReceivablesTest extends TestCase
{
    private function rules(
        int $dueDays = 45,
        float $taxRate = 18.0,
        float $bankRate = 5.5,
        int $multiplier = 3,
        int $readyScore = 85,
    ): Receivables {
        return new Receivables(
            msmeDueDays: $dueDays,
            defaultTaxRate: $taxRate,
            bankRate: $bankRate,
            interestMultiplier: $multiplier,
            weights: [
                'evidence' => 70,
                'buyer_onboarded' => 20,
                'buyer_not_onboarded' => 5,
                'overdue' => 10,
            ],
            financeReadyScore: $readyScore,
        );
    }

    public function test_due_date_follows_the_msme_window(): void
    {
        $this->assertSame('2026-06-05', $this->rules()->dueDate('2026-04-21'));
        $this->assertSame('2026-03-01', $this->rules()->dueDate('2026-01-15'));
    }

    public function test_a_configured_window_replaces_the_default_one(): void
    {
        $this->assertSame('2026-05-06', $this->rules(dueDays: 15)->dueDate('2026-04-21'));
    }

    public function test_gst_is_derived_from_the_base_unless_a_tax_figure_is_given(): void
    {
        $this->assertSame(18000.0, $this->rules()->taxOn(100000));
        $this->assertSame(5000.0, $this->rules()->taxOn(100000, 5000));

        // Zero is an answer, not an omission: "no GST" must survive.
        $this->assertSame(0.0, $this->rules()->taxOn(100000, 0.0));
    }

    public function test_total_is_base_plus_tax(): void
    {
        $this->assertSame(118000.0, $this->rules()->total(100000, 18000));
    }

    #[DataProvider('overdueCases')]
    public function test_overdue_days_count_calendar_days(string $due, string $today, InvoiceStatus $status, int $expected): void
    {
        $this->assertSame($expected, $this->rules()->overdueDays($due, $status, $today));
    }

    /**
     * @return array<string, array{string, string, InvoiceStatus, int}>
     */
    public static function overdueCases(): array
    {
        return [
            'day after due' => ['2026-06-04', '2026-06-05', InvoiceStatus::Raised, 1],
            'due today' => ['2026-06-05', '2026-06-05', InvoiceStatus::Raised, 0],
            'not yet due' => ['2026-07-05', '2026-06-05', InvoiceStatus::Raised, 0],
            'thirty days' => ['2026-05-06', '2026-06-05', InvoiceStatus::Accepted, 30],
            'settled stops ageing' => ['2026-01-01', '2026-06-05', InvoiceStatus::Settled, 0],
        ];
    }

    public function test_an_unparseable_date_never_invents_interest(): void
    {
        $this->assertSame(0, $this->rules()->overdueDays('31 Feb 2026', InvoiceStatus::Raised, '2026-06-05'));
        $this->assertSame(0, $this->rules()->overdueDays('2026-02-30', InvoiceStatus::Raised, '2026-06-05'));
        $this->assertSame(0, $this->rules()->overdueDays(null, InvoiceStatus::Raised, '2026-06-05'));
    }

    public function test_interest_compounds_at_monthly_rests_as_section_16_says(): void
    {
        $rules = $this->rules();

        // 118000 x (5.5 x 3)% / 12 = 1622.50 for one rest. Thirty days is a month, so
        // thirty days is one rest's worth of interest — no more, no less.
        $this->assertSame(1622.5, $rules->interest(118000, 30));

        // Ten days is part of a month: pro rata on the resting balance, 458.33.
        $this->assertSame(458.33, $rules->interest(100000, 10));

        $this->assertSame(0.0, $rules->interest(100000, 0));
        $this->assertSame(0.0, $rules->interest(100000, -5));
    }

    public function test_a_second_rest_charges_interest_on_the_first_rest_s_interest(): void
    {
        $rules = $this->rules();
        $schedule = $rules->interestSchedule(100000, 60);

        // 100000 x 1.375% = 1375.00; 101375 x 1.375% = 1393.91. Simple interest over
        // the same sixty days would be 110.16 less: the compounding *is* the
        // statutory difference, and it is the buyer's money either way.
        $this->assertSame(1375.0, $schedule->periods[0]['interest']);
        $this->assertSame(1393.91, $schedule->periods[1]['interest']);
        $this->assertSame(2768.91, $schedule->total);
        $this->assertSame(2, $schedule->rests());
    }

    public function test_the_schedule_is_the_total_it_prints(): void
    {
        // A claim packet shows the month-wise working and a total. If the two ever
        // disagree the packet is wrong on its face, so the total is the sum.
        $schedule = $this->rules()->interestSchedule(967600, 65);

        $this->assertSame(
            round(array_sum(array_column($schedule->periods, 'interest')), 2),
            $schedule->total,
        );

        // Two rests and five days over: 65 = 30 + 30 + 5.
        $this->assertSame([30, 30, 5], array_column($schedule->periods, 'days'));
        $this->assertSame(29070.75, $schedule->total);
    }

    public function test_the_schedule_carries_the_rate_in_force_for_each_rest(): void
    {
        // The Act applies the bank rate "notified from time to time", so an invoice
        // that sat through a cut is not one rate's worth of interest.
        $rules = new Receivables(
            msmeDueDays: 45,
            defaultTaxRate: 18.0,
            bankRate: 5.5,
            interestMultiplier: 3,
            weights: ['evidence' => 70, 'buyer_onboarded' => 20, 'buyer_not_onboarded' => 5, 'overdue' => 10],
            financeReadyScore: 85,
            rateHistory: [
                ['from' => '2026-01-01', 'rate' => 6.0],
                ['from' => '2026-05-01', 'rate' => 5.5],
            ],
        );

        // Due 1 April, 60 days: the first rest sits under the old rate, the second
        // under the new one.
        $schedule = $rules->interestSchedule(100000, 60, new \DateTimeImmutable('2026-04-01'));

        $this->assertSame(18.0, $schedule->periods[0]['rate']);
        $this->assertSame(16.5, $schedule->periods[1]['rate']);
        $this->assertTrue($schedule->rateChanged);

        // With no history every rest carries the configured rate, and the packet says
        // so rather than implying a rate change was applied.
        $flat = $this->rules()->interestSchedule(100000, 60, new \DateTimeImmutable('2026-04-01'));

        $this->assertFalse($flat->rateChanged);
        $this->assertNotSame($schedule->total, $flat->total);
    }

    public function test_a_rest_charges_a_month_not_thirty_days_of_a_year(): void
    {
        // The old formula charged 30/365ths of the annual rate for thirty days; a
        // monthly rest charges 1/12th, which is more, because the convention makes a
        // month thirty days rather than 30.4. Small, real, and in the supplier's
        // favour — so it is worth asserting rather than discovering in an argument.
        $rules = $this->rules();

        $this->assertSame(1622.5, $rules->interest(118000, 30));
        $this->assertGreaterThan(
            round(118000 * 0.165 * 30 / 365, 2),
            $rules->interest(118000, 30),
        );

        // And over a year the rests compound: 1.375% twelve times, not 16.5% once.
        $this->assertSame(21012.04, $rules->interest(118000, 360));
        $this->assertGreaterThan(
            round(118000 * 0.165, 2),
            $rules->interest(118000, 360),
        );
    }

    public function test_a_higher_configured_bank_rate_raises_the_interest(): void
    {
        $this->assertGreaterThan(
            $this->rules(bankRate: 5.5)->interest(118000, 30),
            $this->rules(bankRate: 6.5)->interest(118000, 30),
        );
    }

    #[DataProvider('ageingCases')]
    public function test_ageing_buckets(int $days, AgeingBucket $expected): void
    {
        $this->assertSame($expected, $this->rules()->ageing($days));
    }

    /**
     * @return array<string, array{int, AgeingBucket}>
     */
    public static function ageingCases(): array
    {
        return [
            'current' => [0, AgeingBucket::Current],
            'negative' => [-4, AgeingBucket::Current],
            'one day' => [1, AgeingBucket::Days1To30],
            'thirty' => [30, AgeingBucket::Days1To30],
            'thirty one' => [31, AgeingBucket::Days31To60],
            'sixty' => [60, AgeingBucket::Days31To60],
            'sixty one' => [61, AgeingBucket::Days61To90],
            'ninety' => [90, AgeingBucket::Days61To90],
            'past ninety' => [91, AgeingBucket::Days90Plus],
        ];
    }

    public function test_balance_never_goes_negative_and_a_settled_invoice_owes_nothing(): void
    {
        $rules = $this->rules();

        $this->assertSame(20000.0, $rules->balance(118000, 98000, InvoiceStatus::Accepted));
        $this->assertSame(0.0, $rules->balance(118000, 130000, InvoiceStatus::Accepted));
        $this->assertSame(0.0, $rules->balance(118000, 0, InvoiceStatus::Settled));
    }

    public function test_readiness_is_evidence_then_buyer_then_overdue(): void
    {
        $rules = $this->rules();

        // Four of four required documents, buyer onboarded: 70 + 20.
        $this->assertSame(90, $rules->readiness(4, 4, TredsOnboarding::Yes, 0));

        // A confirmed "no" is a shorter gap than an unanswered question.
        $this->assertSame(5, $rules->readiness(0, 4, TredsOnboarding::No, 0));
        $this->assertSame(0, $rules->readiness(0, 4, TredsOnboarding::Unknown, 0));

        // Half the evidence, overdue: 35 + 10.
        $this->assertSame(45, $rules->readiness(2, 4, TredsOnboarding::Unknown, 12));

        // Capped, and financeable at the configured score.
        $this->assertSame(100, $rules->readiness(4, 4, TredsOnboarding::Yes, 12));
        $this->assertTrue($rules->isFinanceReady(85));
        $this->assertFalse($rules->isFinanceReady(84));
    }

    public function test_treds_status_reports_why_an_invoice_cannot_be_discounted(): void
    {
        $rules = $this->rules();

        $this->assertSame(TredsStatus::Ready, $rules->tredsStatus(InvoiceStatus::Accepted, TredsOnboarding::Yes));
        $this->assertSame(
            TredsStatus::PendingBuyerOnboard,
            $rules->tredsStatus(InvoiceStatus::Accepted, TredsOnboarding::No)
        );
        $this->assertSame(
            TredsStatus::PendingBuyerOnboard,
            $rules->tredsStatus(InvoiceStatus::Raised, TredsOnboarding::Unknown)
        );
        $this->assertSame(TredsStatus::Financed, $rules->tredsStatus(InvoiceStatus::Financed, TredsOnboarding::Yes));
        $this->assertSame(TredsStatus::Ineligible, $rules->tredsStatus(InvoiceStatus::Disputed, TredsOnboarding::Yes));
        $this->assertSame(TredsStatus::Na, $rules->tredsStatus(InvoiceStatus::Settled, TredsOnboarding::Yes));
        $this->assertSame(TredsStatus::Na, $rules->tredsStatus(InvoiceStatus::Draft, TredsOnboarding::Yes));
    }

    public function test_the_thirty_day_receivable_window_includes_yesterday(): void
    {
        $rules = $this->rules();

        $this->assertTrue($rules->fallsDueWithin('2026-06-05', 30, '2026-06-05'));
        $this->assertTrue($rules->fallsDueWithin('2026-07-05', 30, '2026-06-05'));
        // A day late is still worth showing — it just became overdue.
        $this->assertTrue($rules->fallsDueWithin('2026-06-04', 30, '2026-06-05'));
        $this->assertFalse($rules->fallsDueWithin('2026-06-03', 30, '2026-06-05'));
        $this->assertFalse($rules->fallsDueWithin('2026-07-06', 30, '2026-06-05'));
        $this->assertFalse($rules->fallsDueWithin(null, 30, '2026-06-05'));
    }

    public function test_days_until_is_negative_once_the_date_has_passed(): void
    {
        $rules = $this->rules();

        $this->assertSame(12, $rules->daysUntil('2026-06-17', '2026-06-05'));
        $this->assertSame(0, $rules->daysUntil('2026-06-05', '2026-06-05'));
        $this->assertSame(-3, $rules->daysUntil('2026-06-02', '2026-06-05'));
        $this->assertNull($rules->daysUntil(null, '2026-06-05'));
    }

    public function test_the_service_reads_the_configuration_it_ships_with(): void
    {
        // Guards against a config key being renamed while the service is not:
        // fromConfig() must produce a working ruleset from config/paykaro.php.
        $rules = Receivables::fromConfig();

        $this->assertGreaterThan(0, $rules->msmeDueDays);
        $this->assertGreaterThan(0, $rules->bankRate);
        $this->assertGreaterThan(0, $rules->interestMultiplier);
        $this->assertSame(['evidence', 'buyer_onboarded', 'buyer_not_onboarded', 'overdue'], array_keys($rules->weights));
        $this->assertSame(
            $rules->dueDate('2026-04-21'),
            $this->rules($rules->msmeDueDays)->dueDate('2026-04-21'),
        );
    }
}
