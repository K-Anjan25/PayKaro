<?php

namespace App\Services;

use DateTimeImmutable;

/**
 * The month-wise statement a Section 16 claim is made of.
 *
 * Section 16 of the MSMED Act 2006 does not say "interest at three times the bank
 * rate". It says:
 *
 *   "the buyer shall ... be liable to pay **compound interest with monthly rests**
 *    to the supplier on that amount ... at three times of the bank rate notified by
 *    the Reserve Bank"
 *
 * So the accrual compounds at each monthly rest, and the figure a claim quotes is
 * the sum of the rests — which is why this is a schedule rather than a formula. A
 * tribunal asks for the month-wise working, and the working is the arithmetic: it
 * is not a presentation of interest, it *is* interest.
 *
 * What the Act does not specify is the partial month. A rest has to happen on a
 * date, so this takes a month as 30 days from the due date: complete months each
 * compound, and the days left over accrue simple interest on the compounded
 * balance. That is the convention month-wise statements use in practice, it keeps
 * "one month overdue" meaning exactly one month's interest, and it makes the
 * schedule add up to the number printed beside it.
 *
 * Rates change. The Act applies the bank rate "notified by the Reserve Bank" from
 * time to time, so `rateFor()` lets a caller supply the rate in force on a given
 * date; with only one rate configured, every period uses it and `rateChanged` stays
 * false, which the claim packet reports rather than hides.
 */
final class InterestSchedule
{
    /** A rest is monthly; 30 days is the convention, and it is stated on the packet. */
    public const DAYS_PER_MONTH = 30;

    private const DAYS_PER_YEAR = 365;

    /**
     * @param  list<array{period: int|null, days: int, from: string, to: string, rate: float, opening: float, interest: float, closing: float, partial: bool}>  $periods
     */
    private function __construct(
        public readonly float $principal,
        public readonly int $overdueDays,
        public readonly array $periods,
        public readonly float $total,
        public readonly bool $rateChanged,
    ) {}

    /**
     * @param  callable(DateTimeImmutable): float|null  $rateFor
     *                                                            Rate in force on a date, in per-cent per annum (e.g. 16.5 for 5.5 × 3).
     *                                                            Null means "one rate for the whole schedule".
     */
    public static function build(
        float $principal,
        int $overdueDays,
        float $annualRate,
        ?DateTimeImmutable $dueDate = null,
        ?callable $rateFor = null,
    ): self {
        if ($overdueDays <= 0 || $principal <= 0) {
            return new self(round($principal, 2), max(0, $overdueDays), [], 0.0, false);
        }

        $rests = intdiv($overdueDays, self::DAYS_PER_MONTH);
        $remainder = $overdueDays % self::DAYS_PER_MONTH;

        $balance = round($principal, 2);
        $total = 0.0;
        $periods = [];
        $rateChanged = false;
        $firstRate = null;

        $cursor = $dueDate;

        for ($period = 1; $period <= $rests; $period++) {
            $rate = $rateFor && $cursor ? $rateFor($cursor) : $annualRate;
            $firstRate ??= $rate;
            $rateChanged = $rateChanged || $rate !== $firstRate;

            // A rest charges a whole month's interest at the periodic rate and adds
            // it to the balance: that is what "compounded monthly" means, and
            // rounding each rest to the paisa is what a statement shows.
            $interest = round($balance * ($rate / 100 / 12), 2);
            $closing = round($balance + $interest, 2);

            $periods[] = [
                'period' => $period,
                'days' => self::DAYS_PER_MONTH,
                'from' => $cursor?->format('d M Y') ?? '—',
                'to' => $cursor?->modify('+30 days')->format('d M Y') ?? '—',
                'rate' => $rate,
                'opening' => $balance,
                'interest' => $interest,
                'closing' => $closing,
                'partial' => false,
            ];

            $total += $interest;
            $balance = $closing;
            $cursor = $cursor?->modify('+30 days');
        }

        if ($remainder > 0) {
            $rate = $rateFor && $cursor ? $rateFor($cursor) : $annualRate;
            $firstRate ??= $rate;
            $rateChanged = $rateChanged || $rate !== $firstRate;

            // Part of a month accrues on the compounded balance, pro rata: no rest
            // has fallen, so nothing compounds yet.
            $interest = round($balance * ($rate / 100 / 12) * ($remainder / self::DAYS_PER_MONTH), 2);

            $periods[] = [
                'period' => null,
                'days' => $remainder,
                'from' => $cursor?->format('d M Y') ?? '—',
                'to' => $cursor?->modify("+{$remainder} days")->format('d M Y') ?? '—',
                'rate' => $rate,
                'opening' => $balance,
                'interest' => $interest,
                'closing' => round($balance + $interest, 2),
                'partial' => true,
            ];

            $total += $interest;
        }

        return new self(
            round($principal, 2),
            $overdueDays,
            $periods,
            round($total, 2),
            $rateChanged,
        );
    }

    /**
     * A monthly rate expressed the way a statement reads it: "16.5% a year, 1.375% a
     * month at each rest".
     */
    public function monthlyRate(float $annualRate): float
    {
        return round($annualRate / 12, 6);
    }

    /**
     * The daily rate, for the one place it belongs: telling a member what a day of
     * delay costs from here. The claim itself is always the schedule's total.
     */
    public function dailyRate(float $annualRate): float
    {
        return $annualRate / 100 / self::DAYS_PER_YEAR;
    }

    public function rests(): int
    {
        return count(array_filter($this->periods, fn (array $p) => ! $p['partial']));
    }
}
