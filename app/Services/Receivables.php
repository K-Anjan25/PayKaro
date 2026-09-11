<?php

namespace App\Services;

use App\Enums\AgeingBucket;
use App\Enums\InvoiceStatus;
use App\Enums\TredsOnboarding;
use App\Enums\TredsStatus;
use DateTimeImmutable;
use DateTimeZone;

/**
 * The arithmetic a receivable stands on: due dates, interest, ageing, readiness
 * and whether an invoice is discountable.
 *
 * Deliberately framework-free — it takes plain dates and numbers, not Eloquent
 * models — so the statutory-interest and readiness rules can be reasoned about
 * (and unit-tested) without a database, a session, or a tenant. The models are
 * thin adapters that hand this service the few facts it needs and expose the
 * answers as accessors.
 *
 * All date maths is done on *calendar days* in UTC, so a due date never gains or
 * loses a day to the server's timezone or a DST transition; "today" is taken in
 * the application timezone (config/app.php), which for PayKaro is IST.
 */
final class Receivables
{
    private const SECONDS_PER_DAY = 86400;

    /**
     * @param  array{evidence: int, buyer_onboarded: int, buyer_not_onboarded: int, overdue: int}  $weights
     */
    public function __construct(
        public readonly int $msmeDueDays,
        public readonly float $defaultTaxRate,
        public readonly float $bankRate,
        public readonly int $interestMultiplier,
        public readonly array $weights,
        public readonly int $financeReadyScore,
    ) {}

    public static function fromConfig(): self
    {
        $config = config('paykaro');

        return new self(
            msmeDueDays: (int) $config['msme_due_days'],
            defaultTaxRate: (float) $config['default_tax_rate'],
            bankRate: (float) $config['bank_rate'],
            interestMultiplier: (int) $config['interest_multiplier'],
            weights: (array) $config['readiness_weights'],
            financeReadyScore: (int) $config['finance_ready_score'],
        );
    }

    /* ------------------------------------------------------------------ *
     * Raising an invoice
     * ------------------------------------------------------------------ */

    /**
     * The statutory due date: 45 days from the invoice date unless the
     * workspace says otherwise.
     */
    public function dueDate(string $invoiceDate): string
    {
        $date = $this->day($invoiceDate);

        if ($date === null) {
            return $invoiceDate;
        }

        return $date
            ->modify(sprintf('+%d days', $this->msmeDueDays))
            ->format('Y-m-d');
    }

    /**
     * GST on the base amount. An explicit tax figure always wins; an empty one
     * falls back to the configured default rate.
     */
    public function taxOn(float $baseAmount, ?float $explicitTax = null): float
    {
        $tax = $explicitTax !== null && $explicitTax >= 0
            ? $explicitTax
            : $baseAmount * $this->defaultTaxRate / 100;

        return round($tax, 2);
    }

    public function total(float $baseAmount, float $taxAmount): float
    {
        return round($baseAmount + $taxAmount, 2);
    }

    /* ------------------------------------------------------------------ *
     * Ageing and interest
     * ------------------------------------------------------------------ */

    /**
     * Days past the due date. A settled invoice has stopped ageing; so does a
     * missing or malformed date, which must never invent interest.
     */
    public function overdueDays(?string $dueDate, InvoiceStatus $status, ?string $today = null): int
    {
        if ($status === InvoiceStatus::Settled) {
            return 0;
        }

        $due = $this->day($dueDate);
        $now = $this->day($today) ?? $this->today();

        if ($due === null) {
            return 0;
        }

        return max(0, (int) floor(($now->getTimestamp() - $due->getTimestamp()) / self::SECONDS_PER_DAY));
    }

    /**
     * Statutory interest on delayed payments under the MSMED framework: three
     * times the bank rate, per annum, accrued daily on the whole invoice value.
     */
    public function interest(float $totalAmount, int $overdueDays): float
    {
        if ($overdueDays <= 0) {
            return 0.0;
        }

        $rate = $this->bankRate * $this->interestMultiplier;
        $daily = $rate / 100 / 365;

        return round($totalAmount * $daily * $overdueDays, 2);
    }

    public function ageing(int $overdueDays): AgeingBucket
    {
        return AgeingBucket::fromOverdueDays($overdueDays);
    }

    /**
     * What is still owed. A settled invoice owes nothing whatever the payment
     * rows say, and an overpayment never turns into a negative receivable.
     */
    public function balance(float $totalAmount, float $paidAmount, InvoiceStatus $status): float
    {
        if ($status === InvoiceStatus::Settled) {
            return 0.0;
        }

        return max(0.0, round($totalAmount - $paidAmount, 2));
    }

    /* ------------------------------------------------------------------ *
     * Financing readiness
     * ------------------------------------------------------------------ */

    /**
     * 0-100 score for how ready an invoice is to be discounted or claimed.
     *
     * Evidence completeness carries the bulk of the score (70 by default),
     * buyer TReDS onboarding most of the rest, and past-due-ness a nudge,
     * because an overdue invoice is the one a financier wants first.
     *
     * @param  int  $presentRequiredEvidence  how many of the required documents are ticked
     * @param  int  $requiredEvidenceCount    how many the workspace requires (4)
     */
    public function readiness(
        int $presentRequiredEvidence,
        int $requiredEvidenceCount,
        TredsOnboarding $buyerOnboarding,
        int $overdueDays,
    ): int {
        $share = $presentRequiredEvidence / max(1, $requiredEvidenceCount);
        $score = $share * $this->weights['evidence'];

        $score += match ($buyerOnboarding) {
            TredsOnboarding::Yes => $this->weights['buyer_onboarded'],
            TredsOnboarding::No => $this->weights['buyer_not_onboarded'],
            TredsOnboarding::Unknown => 0,
        };

        if ($overdueDays > 0) {
            $score += $this->weights['overdue'];
        }

        return (int) min(100, round($score));
    }

    public function isFinanceReady(int $score): bool
    {
        return $score >= $this->financeReadyScore;
    }

    /**
     * Whether the invoice can be discounted on TReDS today, and if not, why.
     *
     * A draft is not in the market yet; a dispute freezes the paper; and an
     * invoice against a buyer who has not onboarded is ready *in principle* —
     * which is exactly the gap the finance queue exists to surface.
     */
    public function tredsStatus(
        InvoiceStatus $status,
        TredsOnboarding $buyerOnboarding,
    ): TredsStatus {
        if (in_array($status, [InvoiceStatus::Settled, InvoiceStatus::Draft], true)) {
            return TredsStatus::Na;
        }

        if ($status === InvoiceStatus::Financed) {
            return TredsStatus::Financed;
        }

        if ($status === InvoiceStatus::Disputed) {
            return TredsStatus::Ineligible;
        }

        return $buyerOnboarding->isOnboarded()
            ? TredsStatus::Ready
            : TredsStatus::PendingBuyerOnboard;
    }

    /* ------------------------------------------------------------------ *
     * Cash-flow windows
     * ------------------------------------------------------------------ */

    /**
     * Is this due date inside the next `$days` window (plus the one-day grace
     * for something that just fell due), for the "receivable in 30 days" KPI?
     */
    public function fallsDueWithin(?string $dueDate, int $days, ?string $today = null): bool
    {
        $due = $this->day($dueDate);

        if ($due === null) {
            return false;
        }

        $now = $this->day($today) ?? $this->today();

        $windowEnd = $now->modify(sprintf('+%d days', $days));
        $graceStart = $now->modify('-1 day');

        return $due->getTimestamp() <= $windowEnd->getTimestamp()
            && $due->getTimestamp() >= $graceStart->getTimestamp();
    }

    /**
     * Days until a date, negative when it has passed — used by the dispute
     * deadline callouts ("in 12 days" / "3 days past").
     */
    public function daysUntil(?string $date, ?string $today = null): ?int
    {
        $target = $this->day($date);

        if ($target === null) {
            return null;
        }

        $now = $this->day($today) ?? $this->today();

        return (int) floor(($target->getTimestamp() - $now->getTimestamp()) / self::SECONDS_PER_DAY);
    }

    /* ------------------------------------------------------------------ *
     * Dates
     * ------------------------------------------------------------------ */

    /**
     * A strict `Y-m-d` calendar date at UTC midnight. Anything else (including
     * `2026-02-30`) is null, so a bad date degrades to "no ageing" rather than
     * silently rolling over into a different month.
     */
    private function day(?string $date): ?DateTimeImmutable
    {
        if (! is_string($date) || preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($date), $m) !== 1) {
            return null;
        }

        if (! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d 00:00:00', (int) $m[1], (int) $m[2], (int) $m[3]),
            new DateTimeZone('UTC'),
        );
    }

    private function today(): DateTimeImmutable
    {
        $timezone = $this->timezone();

        $date = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');

        return new DateTimeImmutable($date.' 00:00:00', new DateTimeZone('UTC'));
    }

    private function timezone(): DateTimeZone
    {
        return new DateTimeZone(date_default_timezone_get());
    }
}
