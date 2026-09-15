@php
    $search = (string) request('q', '');
    $buyerFilter = request('buyer');
@endphp

<x-layouts.app title="Invoices" active="invoices">
    <section class="metric-grid metric-grid--4">
        <article class="metric-card metric-card--dark">
            <div class="metric-top">
                <span class="metric-eyebrow">Total outstanding</span>
                <span class="metric-icon">₹</span>
            </div>
            <div class="metric-value num">{{ money($summary->total) }}</div>
            <div class="metric-foot">{{ plural($summary->invoiceCount, 'active invoice') }} tracked</div>
        </article>
        <article class="metric-card metric-card--danger">
            <div class="metric-top">
                <span class="metric-eyebrow">Overdue &gt; {{ config('paykaro.msme_due_days') }} days</span>
                <span class="metric-icon">!</span>
            </div>
            <div class="metric-value num">{{ money($summary->overdue) }}</div>
            <div class="metric-foot">{{ plural($summary->overdueCount, 'statutory default') }}</div>
        </article>
        <article class="metric-card metric-card--brand">
            <div class="metric-top">
                <span class="metric-eyebrow">TReDS discounted</span>
                <span class="metric-icon">↗</span>
            </div>
            <div class="metric-value num">{{ money($financedAmount) }}</div>
            <div class="metric-foot">{{ plural($financedCount, 'funded invoice') }}</div>
        </article>
        <article class="metric-card metric-card--warning">
            <div class="metric-top">
                <span class="metric-eyebrow">Statutory interest claimable</span>
                <span class="metric-icon">%</span>
            </div>
            <div class="metric-value num">{{ money($summary->interest) }}</div>
            <div class="metric-foot">Section 16 · {{ config('paykaro.interest_multiplier') }}× bank rate</div>
        </article>
    </section>

    <div class="screen-head">
        <div>
            <h1 class="pkg-h1">Invoices</h1>
            <p class="pkg-sub">MSME statutory receivable portfolio &amp; TReDS discounting control ledger.</p>
        </div>
        @can('create', App\Models\Invoice::class)
            <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.create') }}">+ New invoice</a>
        @endcan
    </div>

    @php
        $tabs = ['all' => 'All'];
        foreach (App\Enums\InvoiceStatus::cases() as $tabStatus) {
            if ($tabStatus !== App\Enums\InvoiceStatus::Draft) {
                $tabs[$tabStatus->value] = $tabStatus->label();
            }
        }
    @endphp

    <div class="panel-strip">
        <div class="pkg-tabs" style="margin:0;">
            @foreach ($tabs as $key => $label)
                <a class="pkg-tab {{ $status === $key ? 'is-active' : '' }}"
                   href="{{ route('invoices.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search, 'buyer' => $buyerFilter])) }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="pkg-muted">Section 15 MSMED 45-day SLA monitor active</div>
    </div>

    <form class="pkg-card filters-card" method="get" action="{{ route('invoices.index') }}">
        @if ($status !== 'all')
            <input type="hidden" name="status" value="{{ $status }}">
        @endif
        <div class="filters-row">
            <label class="filters-field">
                <span class="sr-only">Buyer</span>
                <select class="pkg-input" name="buyer">
                    <option value="">All Buyers</option>
                    @foreach ($buyers as $buyer)
                        <option value="{{ $buyer->id }}" @selected((string) $buyerFilter === (string) $buyer->id)>{{ $buyer->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="filters-field filters-field--grow">
                <span class="sr-only">Search invoices</span>
                <input class="pkg-input" type="search" name="q" value="{{ $search }}" placeholder="Search invoice number or buyer name...">
            </label>
            <div class="filters-actions">
                <span class="pkg-muted">{{ plural($invoices->total(), 'record') }}</span>
                <button class="pkg-btn pkg-btn--sm pkg-btn--primary" type="submit">Apply</button>
                <a class="pkg-btn pkg-btn--sm" href="{{ route('invoices.index', array_filter(['status' => $status === 'all' ? null : $status])) }}">Reset</a>
            </div>
        </div>
    </form>

    @if ($invoices->isEmpty())
        <div class="pkg-card pkg-empty">
            <h2 class="pkg-h2">{{ $status === 'all' ? 'No invoices yet' : 'Nothing in this state' }}</h2>
            <p class="pkg-sub">
                @if ($search !== '')
                    {{-- The term is echoed back so an empty result cannot be
                         mistaken for an empty book: a search that matches
                         nothing — including one that only matches another
                         tenant's invoice — has to say so. --}}
                    Nothing matched “{{ $search }}”.
                @elseif (filled($buyerFilter))
                    No invoice matches your current filters.
                @elseif ($status === 'all')
                    Raise your first invoice to start tracking receivables.
                @else
                    No invoices are currently {{ strtolower($tabs[$status] ?? $status) }}.
                @endif
            </p>
            @can('create', App\Models\Invoice::class)
                <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.create') }}">+ Raise an invoice</a>
            @endcan
        </div>
    @else
        <div class="pkg-card pkg-tablewrap">
            <table class="pkg-table ledger-table">
                <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Customer</th>
                    <th>Due Date</th>
                    <th>Balance</th>
                    <th>Ageing</th>
                    <th>Status</th>
                    <th>Ready</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($invoices as $invoice)
                    <tr>
                        <td>
                            <a class="pkg-link" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a>
                            <div class="pkg-muted">{{ $invoice->invoice_date?->format('d M Y') }}</div>
                        </td>
                        <td>
                            <strong>{{ $invoice->buyer?->name }}</strong>
                            <div class="pkg-muted">GSTIN: {{ $invoice->buyer?->gstin ?: '—' }}</div>
                        </td>
                        <td class="{{ $invoice->overdueDays() > 0 ? 'pkg-neg' : '' }}">
                            {{ $invoice->due_date?->format('d M Y') }}
                        </td>
                        <td>
                            <strong class="num {{ $invoice->overdueDays() > 0 ? 'pkg-neg' : '' }}">{{ money($invoice->balance()) }}</strong>
                            @if ($invoice->interest() > 0)
                                <div class="pkg-muted pkg-neg">+ {{ money($invoice->interest()) }}</div>
                            @endif
                        </td>
                        <td><span class="metric-pill metric-pill--soft">{{ $invoice->ageing()->value }}</span></td>
                        <td><x-status-badge :status="$invoice->status" /></td>
                        <td class="ready-score-cell">
                            <strong class="num">{{ $invoice->readiness() }}/100</strong>
                            <span class="ready-dot {{ $invoice->isFinanceReady() ? 'is-ready' : ($invoice->readiness() >= 60 ? 'is-mid' : 'is-low') }}"></span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{ $invoices->links('components.pagination', ['noun' => 'invoices']) }}
        </div>
    @endif
</x-layouts.app>
