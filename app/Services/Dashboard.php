<?php

namespace App\Services;

use App\Enums\AgeingBucket;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;

/**
 * Aggregates the workspace's receivables into the figures the dashboard and
 * reports pages show.
 *
 * Everything is computed from the same eagerly-loaded set (`withMetrics()`),
 * so a book of N invoices is three queries rather than 6N, and every figure on
 * the page is derived from one definition of "outstanding".
 */
class Dashboard
{
    /**
     * Days that count as "coming up" for the receivable-in-30-days KPI.
     */
    private const UPCOMING_DAYS = 30;

    public function __construct(protected Receivables $receivables) {}

    public function overview(): DashboardSummary
    {
        $invoices = Invoice::query()->withMetrics()->latestFirst()->get();

        return $this->summarise($invoices);
    }

    public function summarise(Collection $invoices): DashboardSummary
    {
        $total = 0.0;
        $overdue = 0.0;
        $overdueCount = 0;
        $interest = 0.0;
        $dueIn30Days = 0.0;
        $financeReady = 0;

        /** @var array<string, array{count: int, amount: float}> $buckets */
        $buckets = [];

        foreach (AgeingBucket::cases() as $bucket) {
            $buckets[$bucket->value] = ['count' => 0, 'amount' => 0.0];
        }

        foreach ($invoices as $invoice) {
            // A draft is not a receivable yet, so it is absent from every
            // figure here — including ageing, which used to count it.
            if (! $invoice->status->countsTowardsReceivables()) {
                continue;
            }

            $balance = $invoice->balance();
            $total += $balance;
            $interest += $invoice->interest();

            if ($invoice->overdueDays() > 0 && $invoice->status !== InvoiceStatus::Settled) {
                $overdue += $balance;
                $overdueCount++;
            }

            $bucket = $invoice->ageing();
            $buckets[$bucket->value]['amount'] += $balance;
            $buckets[$bucket->value]['count']++;

            if ($invoice->isFinanceReady()) {
                $financeReady++;
            }

            if ($this->receivables->fallsDueWithin($invoice->dueDateValue(), self::UPCOMING_DAYS)) {
                $dueIn30Days += (float) $invoice->total_amount;
            }
        }

        $rows = [];

        foreach (AgeingBucket::cases() as $bucket) {
            $rows[] = [
                'bucket' => $bucket,
                'count' => $buckets[$bucket->value]['count'],
                'amount' => round($buckets[$bucket->value]['amount'], 2),
                'percent' => $total > 0
                    ? round($buckets[$bucket->value]['amount'] / $total * 100, 1)
                    : 0.0,
            ];
        }

        return new DashboardSummary(
            total: round($total, 2),
            overdue: round($overdue, 2),
            overdueCount: $overdueCount,
            interest: round($interest, 2),
            dueIn30Days: round($dueIn30Days, 2),
            invoiceCount: $invoices->count(),
            financeReadyCount: $financeReady,
            buckets: $rows,
        );
    }

    /**
     * Outstanding balance grouped by buyer, largest first — the reports page.
     *
     * @return list<array{name: string, amount: float}>
     */
    public function outstandingByBuyer(): array
    {
        $rows = [];

        foreach (Invoice::query()->withMetrics()->open()->with('buyer')->get() as $invoice) {
            $name = $invoice->buyer?->name ?? 'Unknown buyer';
            $rows[$name] = ($rows[$name] ?? 0.0) + $invoice->balance();
        }

        arsort($rows);

        return array_map(
            fn (string $name, float $amount) => ['name' => $name, 'amount' => round($amount, 2)],
            array_keys($rows),
            array_values($rows),
        );
    }

    /**
     * Invoices a financier could take today, plus the ones blocked only by
     * buyer onboarding — the gap the queue exists to close.
     *
     * @return Collection<int, Invoice>
     */
    public function financeQueue(): Collection
    {
        return Invoice::query()
            ->withMetrics()
            ->latestFirst()
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->treds()->isInFinanceQueue())
            ->values();
    }
}
