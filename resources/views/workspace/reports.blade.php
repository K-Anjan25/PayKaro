@php
    $averageOverdue = $openInvoices->count() > 0 ? (int) round($openInvoices->avg(fn ($invoice) => $invoice->overdueDays())) : 0;
    $onTimeRate = $openInvoices->count() > 0 ? round($openInvoices->filter(fn ($invoice) => $invoice->overdueDays() <= 0)->count() / $openInvoices->count() * 100, 1) : 100.0;

    $buyerRows = $openInvoices
        ->groupBy(fn ($invoice) => $invoice->buyer?->id ?? 0)
        ->map(function ($group) {
            $first = $group->first();

            return [
                'name' => $first?->buyer?->name ?? 'Unknown buyer',
                'gstin' => $first?->buyer?->gstin ?: '—',
                'count' => $group->count(),
                'total' => round($group->sum(fn ($invoice) => $invoice->balance()), 2),
                'overdue' => round($group->filter(fn ($invoice) => $invoice->overdueDays() > 0)->sum(fn ($invoice) => $invoice->balance()), 2),
                'weighted' => (int) round($group->avg(fn ($invoice) => $invoice->overdueDays())),
                'interest' => round($group->sum(fn ($invoice) => $invoice->interest()), 2),
                'treds' => $first?->buyer?->treds_onboarded,
            ];
        })
        ->sortByDesc('total')
        ->values();
@endphp

<x-layouts.app title="Reports" active="reports">
    <div class="screen-head">
        <div>
            <h1 class="pkg-h1">MSMED Receivables &amp; Ageing Reports</h1>
            <p class="pkg-sub">Outstanding concentration, ageing analysis and interest accruals across the live ledger.</p>
        </div>
        <div class="screen-head-actions">
            <span class="metric-pill">Read-only ledger</span>
            <a class="pkg-btn pkg-btn--sm" href="{{ route('treds') }}">TReDS auction portal →</a>
        </div>
    </div>

    <section class="metric-grid metric-grid--4">
        <article class="metric-card metric-card--dark">
            <div class="metric-eyebrow">Total tracked receivables</div>
            <div class="metric-value num">{{ money($summary->total) }}</div>
            <div class="metric-foot">{{ plural($summary->invoiceCount, 'invoice') }} in scope</div>
        </article>
        <article class="metric-card metric-card--soft">
            <div class="metric-eyebrow">Average ageing</div>
            <div class="metric-value num">{{ $averageOverdue }} days</div>
            <div class="metric-foot">Relative to the due date</div>
        </article>
        <article class="metric-card metric-card--danger">
            <div class="metric-eyebrow">Section 16 interest accrued</div>
            <div class="metric-value num">{{ money($summary->interest) }}</div>
            <div class="metric-foot">Accruing from overdue invoices</div>
        </article>
        <article class="metric-card metric-card--brand">
            <div class="metric-eyebrow">On-time clearing rate</div>
            <div class="metric-value num">{{ number_format($onTimeRate, 1) }}%</div>
            <div class="metric-foot">Invoices still within terms</div>
        </article>
    </section>

    <section class="pkg-card">
        <div class="pkg-cardhead">
            <div>
                <h2 class="pkg-h2">Ageing Matrix (5-bucket model)</h2>
                <p class="pkg-sub">Granular classification against the live due-window thresholds.</p>
            </div>
            <span class="metric-pill">Based on stored invoice dates</span>
        </div>

        <div class="ageing-strip">
            @foreach ($summary->buckets as $bucket)
                <span class="ageing-strip-segment ageing-strip-segment--{{ $bucket['bucket']->barColor() }}" style="width:{{ max(6, $bucket['percent']) }}%"></span>
            @endforeach
        </div>

        <div class="bucket-mini-grid">
            @foreach ($summary->buckets as $bucket)
                <article class="bucket-mini-card">
                    <div class="bucket-mini-top">
                        <span class="bucket-mini-dot bucket-mini-dot--{{ $bucket['bucket']->barColor() }}"></span>
                        <strong>{{ $bucket['bucket']->value }}</strong>
                        <span class="metric-pill metric-pill--soft">{{ $bucket['percent'] }}%</span>
                    </div>
                    <div class="bucket-mini-value num">{{ money_compact($bucket['amount']) }}</div>
                    <div class="pkg-muted">{{ plural($bucket['count'], 'invoice') }}</div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="pkg-card pkg-tablewrap">
        <div class="pkg-cardhead">
            <div>
                <h2 class="pkg-h2">Outstanding by buyer</h2>
                <p class="pkg-sub">Weighted concentration, overdue balance and accrued interest per counterparty.</p>
            </div>
            <span class="metric-pill">Read-only MSME audit record</span>
        </div>

        @if ($buyerRows->isEmpty())
            <div class="pkg-empty">
                <p class="pkg-sub">No outstanding invoices.</p>
            </div>
        @else
            <table class="pkg-table ledger-table">
                <thead>
                <tr>
                    <th>Buyer legal entity</th>
                    <th>Active invoices</th>
                    <th>Total balance</th>
                    <th>Overdue balance</th>
                    <th>Weighted ageing</th>
                    <th>MSMED interest accrued</th>
                    <th>TReDS status</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($buyerRows as $row)
                    <tr>
                        <td>
                            <strong>{{ $row['name'] }}</strong>
                            <div class="pkg-muted">GSTIN: {{ $row['gstin'] }}</div>
                        </td>
                        <td><span class="metric-pill metric-pill--soft">{{ plural($row['count'], 'bill') }}</span></td>
                        <td><strong class="num">{{ money($row['total']) }}</strong></td>
                        <td><strong class="num {{ $row['overdue'] > 0 ? 'pkg-neg' : '' }}">{{ money($row['overdue']) }}</strong></td>
                        <td><span class="metric-pill metric-pill--soft">{{ $row['weighted'] }} days</span></td>
                        <td><strong class="num {{ $row['interest'] > 0 ? 'pkg-neg' : '' }}">{{ money($row['interest']) }}</strong></td>
                        <td>
                            @if ($row['treds'])
                                <x-treds-badge :treds="$row['treds']" />
                            @else
                                <span class="metric-pill metric-pill--soft">Unknown</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</x-layouts.app>
