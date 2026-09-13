<?php

namespace App\Services;

use App\Enums\AgeingBucket;
use App\Enums\InvoiceStatus;

/**
 * The numbers behind the overview page.
 *
 * A readonly snapshot rather than a bag of array keys: the dashboard, the
 * reports page and the ageing chart all read the same figures, and the
 * per-bucket rows are computed once (count, amount, share) so the chart bars and
 * the pipeline list cannot drift apart.
 */
final class DashboardSummary
{
    /**
     * @param  list<array{bucket: AgeingBucket, count: int, amount: float, percent: float}>  $buckets
     */
    public function __construct(
        public readonly float $total,
        public readonly float $overdue,
        public readonly int $overdueCount,
        public readonly float $interest,
        public readonly float $dueIn30Days,
        public readonly int $invoiceCount,
        public readonly int $financeReadyCount,
        public readonly array $buckets,
    ) {}

    public function maxBucketAmount(): float
    {
        $amounts = array_map(fn (array $row) => $row['amount'], $this->buckets);

        return $amounts === [] ? 0.0 : max($amounts);
    }

    /**
     * The chart's axis maximum, rounded up to a "nice" value (1, 2, 2.5 or 5
     * times the leading magnitude) so the gridlines land on readable numbers
     * instead of the largest ragged balance.
     */
    public function axisMax(): float
    {
        $max = $this->maxBucketAmount();

        if ($max <= 0) {
            return 1.0;
        }

        $magnitude = 10 ** floor(log10($max));

        foreach ([1, 2, 2.5, 5, 10] as $multiplier) {
            if ($magnitude * $multiplier >= $max) {
                return max(1.0, $magnitude * $multiplier);
            }
        }

        return max(1.0, $magnitude * 10);
    }

    /**
     * Y-axis tick values, highest first, including zero at the base.
     *
     * @return list<int>
     */
    public function ticks(int $count = 4): array
    {
        $axis = $this->axisMax();

        return array_values(array_map(
            fn (int $step) => (int) round($axis * $step / $count),
            range($count, 0),
        ));
    }

    /**
     * Bar height as a percentage of the axis maximum, with a floor so a
     * non-zero balance is always visible next to a zero one.
     */
    public function bucketHeight(array $row): float
    {
        $axis = $this->axisMax();

        return $axis <= 0 ? 4.0 : max(4.0, round($row['amount'] / $axis * 100, 2));
    }

    public function bucket(AgeingBucket $bucket): ?array
    {
        foreach ($this->buckets as $row) {
            if ($row['bucket'] === $bucket) {
                return $row;
            }
        }

        return null;
    }

    public function hasOverdue(): bool
    {
        return $this->overdueCount > 0;
    }

    public function isEmpty(): bool
    {
        return $this->invoiceCount === 0;
    }

    /**
     * Statuses still moving through the pipeline, for the empty-state hint.
     *
     * @return list<array{status: InvoiceStatus, count: int}>
     */
    public static function pipelineStatuses(): array
    {
        return array_map(
            fn (InvoiceStatus $status) => ['status' => $status, 'count' => 0],
            InvoiceStatus::pipeline(),
        );
    }
}
