<x-layouts.app title="Overview" active="dashboard">
    <div class="pkg-crumbs">
        <a href="{{ route('dashboard') }}">Home</a>
        <span class="pkg-crumbs-sep">›</span>
        <strong>Overview</strong>
    </div>

    <div class="pkg-pagehead">
        <div>
            <h1 class="pkg-h1">Overview</h1>
            <p class="pkg-sub">Here's what's happening with your receivables.</p>
        </div>
        <div class="pkg-pagehead-actions">
            <span class="pkg-filter">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                {{ now()->startOfMonth()->format('d M') }} – {{ now()->format('d M Y') }}
            </span>
            <a class="pkg-btn pkg-btn--sm pkg-btn--primary" href="{{ route('invoices.create') }}">+ New Invoice</a>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="pkg-grid pkg-grid--4">
        <x-kpi-card label="Outstanding" :value="money($summary->total)"
                    :trend="plural($summary->invoiceCount, 'invoice').' in the book'"
                    trend-direction="none" tone="blue" />

        <x-kpi-card label="Overdue" :value="money($summary->overdue)"
                    :trend="$summary->overdueCount > 0
                        ? plural($summary->overdueCount, 'invoice').' past the due date'
                        : 'Nothing past its due date'"
                    :trend-direction="$summary->hasOverdue() ? 'down' : 'none'"
                    tone="red">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </x-kpi-card>

        <x-kpi-card label="Interest" :value="money($summary->interest)"
                    :trend="'accruing at '.config('paykaro.interest_multiplier').'× a '.config('paykaro.bank_rate').'% bank rate'"
                    trend-direction="up" tone="amber">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>
        </x-kpi-card>

        <x-kpi-card label="Receivable in 30d" :value="money($summary->dueIn30Days)"
                    trend="falls due in the next 30 days"
                    trend-direction="none" tone="blue">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        </x-kpi-card>
    </div>

    {{-- Needs attention --}}
    @if ($alerts->isNotEmpty())
        <div class="pkg-card is-accent">
            <div class="pkg-cardhead">
                <h2 class="pkg-h2">Needs attention</h2>
                @can('markRead', App\Models\Alert::class)
                    <form method="post" action="{{ route('alerts.read') }}">
                        @csrf
                        <button class="pkg-btn pkg-btn--sm pkg-btn--ghost" type="submit">Dismiss all</button>
                    </form>
                @endcan
            </div>
            <ul class="pkg-alerts" style="margin:0;">
                @foreach ($alerts as $alert)
                    <li class="pkg-alert{{ $alert->tone() ? ' pkg-alert--'.$alert->tone() : '' }}">
                        <span class="pkg-alert-dot"></span>
                        <span>{{ $alert->message }}</span>
                        @if ($alert->invoice)
                            <a class="pkg-alert-inv" href="{{ route('invoices.show', $alert->invoice) }}">{{ $alert->invoice->number }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="pkg-grid pkg-grid--2">
        {{-- Ageing chart --}}
        <div class="pkg-card">
            <div class="pkg-cardhead">
                <h2 class="pkg-h2">Ageing Summary</h2>
                <span class="pkg-filter" style="padding:.35rem .6rem;">As on {{ now()->format('d M Y') }}</span>
            </div>
            <div class="pkg-ageing-wrap">
                <div class="pkg-ageing-yaxis">
                    @foreach ($summary->ticks() as $tick)
                        <span class="pkg-yaxis num">{{ money_compact($tick) }}</span>
                    @endforeach
                </div>
                <div class="pkg-ageing">
                    <div class="pkg-ageing-grid">
                        @foreach ($summary->ticks() as $tick)
                            <span class="pkg-hline"></span>
                        @endforeach
                    </div>
                    @foreach ($summary->buckets as $bucket)
                        <div class="col">
                            <div class="barwrap">
                                <div class="bar bar--{{ $bucket['bucket']->barColor() }}" style="height:{{ $summary->bucketHeight($bucket) }}%">
                                    <span class="val num">{{ money($bucket['amount']) }}</span>
                                </div>
                            </div>
                            <div class="lab">{{ $bucket['bucket']->chartLabel() }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Pipeline --}}
        <div class="pkg-card">
            <div class="pkg-cardhead">
                <h2 class="pkg-h2">Receivables Status Pipeline</h2>
                <a class="pkg-btn pkg-btn--sm pkg-btn--ghost" href="{{ route('reports') }}">View all →</a>
            </div>
            <ul class="pkg-pipe-list">
                @foreach ($summary->buckets as $bucket)
                    <li class="pkg-pipe-row">
                        <span class="pkg-pipe-dot" style="background:var(--n-{{ $bucket['bucket'] === App\Enums\AgeingBucket::Current ? 'success' : $bucket['bucket']->barColor() }});"></span>
                        <span class="pkg-pipe-name">{{ $bucket['bucket']->label() }}</span>
                        <span class="pkg-pipe-count">{{ plural($bucket['count'], 'invoice') }}</span>
                        <span class="pkg-pipe-right">
                            <span class="pkg-pipe-amt num">{{ money($bucket['amount']) }}</span>
                            <span class="pkg-pipe-pct pkg-badge--{{ $bucket['bucket']->badgeTone() }}">{{ $bucket['percent'] }}%</span>
                        </span>
                    </li>
                @endforeach
            </ul>
            <div class="pkg-pipe-total">
                <span>Total</span>
                <span class="num">{{ money($summary->total) }}</span>
            </div>
        </div>
    </div>

    {{-- Recent invoices --}}
    <div class="pkg-card pkg-tablewrap">
        <div class="pkg-cardhead">
            <h2 class="pkg-h2">Recent Invoices</h2>
            <div class="pkg-toolbar">
                <a class="pkg-btn pkg-btn--sm" href="{{ route('invoices.index') }}">All invoices</a>
                <a class="pkg-btn pkg-btn--sm pkg-btn--primary" href="{{ route('invoices.create') }}">+ New Invoice</a>
            </div>
        </div>

        @if ($recent->isEmpty())
            <div class="pkg-empty" style="padding:2.5rem 1rem;">
                <p class="pkg-sub">No invoices yet.</p>
                <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.create') }}">+ Raise an invoice</a>
            </div>
        @else
            <table class="pkg-table">
                <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Invoice Date</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Outstanding</th>
                    <th>Status</th>
                    <th>Days Overdue</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($recent as $invoice)
                    <tr>
                        <td>
                            <a class="pkg-link" href="{{ route('invoices.show', $invoice) }}"><strong>{{ $invoice->number }}</strong></a>
                        </td>
                        <td>{{ $invoice->buyer?->name }}</td>
                        <td>{{ $invoice->invoice_date?->format('d M Y') }}</td>
                        <td>{{ $invoice->due_date?->format('d M Y') }}</td>
                        <td class="num">{{ money($invoice->total_amount) }}</td>
                        <td class="num">{{ money($invoice->balance()) }}</td>
                        <td><x-health-badge :health="$invoice->health()" /></td>
                        <td class="num">{{ $invoice->overdueDays() > 0 ? $invoice->overdueDays() : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <div class="pkg-pagination">
                <span>Showing {{ min(5, $recent->count()) }} of {{ $summary->invoiceCount }} invoices</span>
            </div>
        @endif
    </div>
</x-layouts.app>
