@php
    $available = round($ready->sum(fn ($invoice) => $invoice->balance()), 2);
    $avgScore = $queue->count() > 0 ? (int) round($queue->avg(fn ($invoice) => $invoice->readiness())) : 0;
    $platformRates = ['RXIL' => 8.10, 'Invoicemart' => 8.25, 'M1xchange' => 8.40];
@endphp

<x-layouts.app title="Finance" active="treds">
    <section class="pkg-card finance-topbar">
        <div class="finance-rate"><span class="finance-dot"></span> TReDS institutional liquidity</div>
        @foreach ($platformRates as $platform => $rate)
            <div class="finance-rate">{{ $platform }} Live: <strong>{{ number_format($rate, 2) }}% avg</strong></div>
        @endforeach
    </section>

    @if ($queue->isEmpty())
        <div class="pkg-card pkg-empty">
            <h2 class="pkg-h2">Nothing in the queue</h2>
            <p class="pkg-sub">An invoice enters the queue once it is raised and not yet settled or disputed.</p>
            <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.create') }}">+ Raise an invoice</a>
        </div>
    @else
        <section class="finance-summary-grid">
            <div class="pkg-card finance-summary-card">
                <div class="pkg-cardhead">
                    <div>
                        <span class="metric-pill">Auction ready</span>
                        <h1 class="pkg-h1" style="margin-top:.75rem;">Finance-ready</h1>
                        <p class="pkg-sub">Needs ≥ {{ config('paykaro.finance_ready_score') }}/100 readiness and buyer onboarding.</p>
                    </div>
                    <div class="finance-summary-count">{{ $ready->count() }}<span>of {{ $queue->count() }} qualified</span></div>
                </div>
                <div class="metric-eyebrow">Total available capital immediately</div>
                <div class="metric-value num">{{ money($available) }}</div>
                <div class="finance-progress"><span style="width:{{ $queue->count() > 0 ? max(8, round($ready->count() / $queue->count() * 100)) : 8 }}%"></span></div>
                <div class="finance-foot-row">
                    <span>Next auction clearing time: {{ now()->format('H:i') }} IST</span>
                    <span>Bids settle before the window closes</span>
                </div>
            </div>

            <aside class="pkg-card finance-index-card">
                <div class="metric-eyebrow">Portfolio readiness index</div>
                <div class="finance-index-wrap">
                    <div class="readiness-ring">
                        <svg viewBox="0 0 120 120" aria-hidden="true">
                            <circle cx="60" cy="60" r="48" pathLength="100"></circle>
                            <circle class="ring-progress" cx="60" cy="60" r="48" pathLength="100" style="stroke-dasharray: {{ $avgScore }}, 100"></circle>
                        </svg>
                        <div class="readiness-ring-copy"><strong>{{ $avgScore }}</strong><span>avg pts</span></div>
                    </div>
                    <div>
                        <h2 class="pkg-h2">{{ $avgScore >= config('paykaro.finance_ready_score') ? 'Auction-ready book' : 'Near-threshold capital' }}</h2>
                        <p class="pkg-sub">{{ money($queue->reject(fn ($invoice) => $invoice->isFinanceReady())->sum(fn ($invoice) => $invoice->balance())) }} currently held up in verification pipelines.</p>
                    </div>
                </div>
                <div class="support-card support-card--inline" style="margin-top:1rem;">
                    <span>Automatic TReDS push</span>
                    <strong>{{ $ready->isNotEmpty() ? 'Active' : 'Standby' }}</strong>
                </div>
            </aside>
        </section>

        <section class="section-head-inline">
            <div>
                <h2 class="pkg-h2">Ready to Discount Today</h2>
                <p class="pkg-sub">Invoices that already meet the product's evidence and TReDS thresholds.</p>
            </div>
            <span class="metric-pill">{{ plural($ready->count(), 'invoice') }}</span>
        </section>

        <div class="finance-ready-list">
            @foreach ($ready as $index => $invoice)
                <article class="pkg-card finance-strip">
                    <div class="finance-strip-main">
                        <div class="finance-strip-id">{{ $invoice->number }}</div>
                        <h3>{{ $invoice->buyer?->name }}</h3>
                        <p class="pkg-sub">GSTIN: {{ $invoice->buyer?->gstin ?: '—' }} · Due {{ $invoice->due_date?->format('d M Y') }}</p>
                    </div>
                    <div class="finance-strip-metrics">
                        <div><span class="metric-eyebrow">Face value</span><strong class="num">{{ money($invoice->balance()) }}</strong></div>
                        <div><span class="metric-eyebrow">Readiness</span><strong>{{ $invoice->readiness() }}/100</strong></div>
                        <div><span class="metric-eyebrow">Best bid</span><strong>{{ number_format(array_values($platformRates)[$index % count($platformRates)], 2) }}% p.a.</strong></div>
                    </div>
                    <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.show', $invoice) }}">Discount now / accept bid</a>
                </article>
            @endforeach
        </div>

        <section class="pkg-card">
            <div class="pkg-cardhead">
                <div>
                    <h2 class="pkg-h2">Blocked / Action Required</h2>
                    <p class="pkg-sub">Receivables that cannot be presented on exchange until the blocker is resolved.</p>
                </div>
                <span class="metric-pill metric-pill--danger">{{ plural($blocked->count(), 'invoice held') }}</span>
            </div>

            <div class="pkg-tablewrap" style="padding:0; margin-bottom:0;">
                <table class="pkg-table ledger-table">
                    <thead>
                    <tr>
                        <th>Invoice &amp; buyer</th>
                        <th>Amount</th>
                        <th>Clearance blocker reason</th>
                        <th>Score</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($blocked as $invoice)
                        <tr>
                            <td>
                                <strong>{{ $invoice->number }}</strong>
                                <div class="pkg-muted">{{ $invoice->buyer?->name }}</div>
                            </td>
                            <td>
                                <strong class="num">{{ money($invoice->balance()) }}</strong>
                                <div class="pkg-muted">Due {{ $invoice->due_date?->format('d M Y') }}</div>
                            </td>
                            <td>
                                <strong>
                                    {{ $invoice->treds() === App\Enums\TredsStatus::PendingBuyerOnboard
                                        ? 'Buyer not on TReDS platform'
                                        : 'Missing checklist evidence' }}
                                </strong>
                                <div class="pkg-muted">
                                    {{ $invoice->treds() === App\Enums\TredsStatus::PendingBuyerOnboard
                                        ? 'Buyer onboarding must be confirmed before auctioning the invoice.'
                                        : 'Complete the required evidence rows to unlock financing.' }}
                                </div>
                            </td>
                            <td><span class="metric-pill metric-pill--soft">{{ $invoice->readiness() }}/100</span></td>
                            <td><a class="pkg-btn pkg-btn--sm" href="{{ route('invoices.show', $invoice) }}">Open invoice</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-layouts.app>
