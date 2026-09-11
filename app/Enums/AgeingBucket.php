<?php

namespace App\Enums;

/**
 * Receivable ageing, measured in days past the due date.
 *
 * The bucket drives the ageing chart, the pipeline list and the interest
 * narrative, so all of them agree on one definition instead of three copies of
 * a threshold ladder.
 */
enum AgeingBucket: string
{
    case Current = 'current';
    case Days1To30 = '1-30';
    case Days31To60 = '31-60';
    case Days61To90 = '61-90';
    case Days90Plus = '90+';

    /**
     * Label used in tables and the pipeline list.
     */
    public function label(): string
    {
        return match ($this) {
            self::Current => 'Current',
            self::Days1To30 => '1–30d',
            self::Days31To60 => '31–60d',
            self::Days61To90 => '61–90d',
            self::Days90Plus => '90+',
        };
    }

    /**
     * Label under the ageing chart bars. The chart reads "days since raised"
     * to a supplier, so the axis is phrased that way.
     */
    public function chartLabel(): string
    {
        return match ($this) {
            self::Current => '0–30 Days',
            self::Days1To30 => '31–60 Days',
            self::Days31To60 => '61–90 Days',
            self::Days61To90 => '91–120 Days',
            self::Days90Plus => '120+ Days',
        };
    }

    /**
     * Bar colour in the ageing chart.
     */
    public function barColor(): string
    {
        return match ($this) {
            self::Current, self::Days1To30 => 'blue',
            self::Days31To60 => 'amber',
            self::Days61To90 => 'orange',
            self::Days90Plus => 'red',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Current => 'success',
            self::Days1To30 => 'info',
            self::Days31To60, self::Days61To90 => 'warning',
            self::Days90Plus => 'danger',
        };
    }

    public static function fromOverdueDays(int $days): self
    {
        return match (true) {
            $days <= 0 => self::Current,
            $days <= 30 => self::Days1To30,
            $days <= 60 => self::Days31To60,
            $days <= 90 => self::Days61To90,
            default => self::Days90Plus,
        };
    }
}
