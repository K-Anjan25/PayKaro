@php
    $instantPayout = round($readyAmount * 0.91, 2);
@endphp

<x-layouts.app title="Overview" active="dashboard">
    <div class="screen-head">
        <div>
            <div class="screen-kicker">Statutory receivables dashboard</div>
            <h1 class="pkg-h1">Sovereign Liquidity &amp; Ledger Overview</h1>
            <p class="pkg-sub">Your live MSME book — what is outstanding, what is overdue, what can be financed today and what needs action first.</p>
        </div>
        <div class="screen-head-actions">
            <span class="metric-pill">Sec. 15–24 MSMED compliant</span>
            <span class="metric-pill">{{ config('paykaro.interest_multiplier') }}× bank rate · accruing daily</span>
        </div>
    </div>

    {{-- The first-run checklist (WIREFRAME_AUDIT §6, WIREFRAMES.md: "sign-up lands
         straight on the dashboard"). It reads the workspace's own state, states the
         next step, and disappears once there is nothing left to say. --}}
    @if (! $onboarding['complete'])
        <section class="pkg-card" id="get-started">
            <div class="pkg-cardhead">
                <div>
                    <div class="screen-kicker">Getting started</div>
                    <h2 class="pkg-h2">Four steps to a finance-ready book</h2>
                    <p class="pkg-sub">This card disappears when the last one is done. Nothing here is a tour: each step is something the workspace does.</p>
                </div>
                @php $done = collect($onboarding['steps'])->where('done', true)->count(); @endphp
                <span class="metric-pill">{{ $done }} of {{ count($onboarding['steps']) }} done</span>
            </div>

            <ol class="onboarding-steps">
                @foreach ($onboarding['steps'] as $step)
                    <li class="onboarding-step {{ $step['done'] ? 'is-done' : '' }}">
                        <span class="onboarding-mark" aria-hidden="true">{{ $step['done'] ? '✓' : $loop->iteration }}</span>
                        <div>
                            <strong>{{ $step['title'] }}</strong>
                            <p>{{ $step['body'] }}</p>
                        </div>
                        @unless ($step['done'])
                            <a class="pkg-btn pkg-btn--sm" href="{{ $step['href'] }}">Start</a>
                        @endunless
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="metric-grid metric-grid--4">
        <article class="metric-card metric-card--dark">
            <div class="metric-top">
                <span class="metric-eyebrow">Outstanding</span>
                <span class="metric-icon">₹</span>
            </div>
            <div class="metric-value num">{{ money($summary->total) }}</div>
            <div class="metric-foot">{{ plural($summary->invoiceCount, 'active invoice') }} tracked</div>
        </article>

        <article class="metric-card metric-card--danger">
            <div class="metric-top">
                <span class="metric-eyebrow">Overdue</span>
                <span class="metric-pill metric-pill--danger">{{ plural($summary->overdueCount, 'statutory default') }}</span>
            </div>
            <div class="metric-value num">{{ money($summary->overdue) }}</div>
            <div class="metric-foot">{{ $summary->overdueCount > 0 ? 'Exceeds the statutory due window' : 'Nothing beyond the due date' }}</div>
        </article>

        <article class="metric-card metric-card--warning">
            <div class="metric-top">
                <span class="metric-eyebrow">Interest</span>
                <span class="metric-icon">%</span>
            </div>
            <div class="metric-value num">{{ money($summary->interest) }}</div>
            <div class="metric-foot">Section 16 at {{ rtrim(rtrim(number_format(config('paykaro.bank_rate'), 2, '.', ''), '0'), '.') }}% × {{ config('paykaro.interest_multiplier') }}</div>
        </article>

        <article class="metric-card metric-card--brand">
            <div class="metric-top">
                <span class="metric-eyebrow">Receivable in 30d</span>
                <span class="metric-icon">⏱</span>
            </div>
            <div class="metric-value num">{{ money($summary->dueIn30Days) }}</div>
            <div class="metric-foot">Falling due in the next 30 days</div>
        </article>
    </section>

    @if ($alerts->isNotEmpty())
        <section class="pkg-card alert-desk">
            <div class="pkg-cardhead">
                <div>
                    <h2 class="pkg-h2">Needs attention</h2>
                    <p class="pkg-sub">Critical deadlines, missing evidence and financing blockers surfaced from the live ledger.</p>
                </div>
                @can('markRead', App\Models\Alert::class)
                    <form method="post" action="{{ route('alerts.read') }}">
                        @csrf
                        <button class="pkg-btn pkg-btn--sm pkg-btn--outline" type="submit">Dismiss all</button>
                    </form>
                @endcan
            </div>

            <div class="alert-list">
                @foreach ($alerts as $alert)
                    <article class="alert-row">
                        <div class="alert-copy">
                            <div class="alert-title">
                                @if ($alert->invoice)
                                    <a class="pkg-link" href="{{ route('invoices.show', $alert->invoice) }}">{{ $alert->invoice->number }}</a>
                                @endif
                                <span>{{ $alert->message }}</span>
                            </div>
                            <div class="pkg-muted">{{ $alert->invoice?->buyer?->name ?? 'Receivables workflow alert' }}</div>
                        </div>
                        @if ($alert->invoice)
                            <a class="pkg-btn pkg-btn--sm {{ $alert->invoice->status === App\Enums\InvoiceStatus::Disputed ? 'pkg-btn--primary' : '' }}" href="{{ route('invoices.show', $alert->invoice) }}">
                                {{ $alert->invoice->status === App\Enums\InvoiceStatus::Disputed ? 'Docket filing packet' : 'Open invoice' }}
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="overview-split">
        <div class="pkg-card">
            <div class="pkg-cardhead">
                <div>
                    <div class="screen-kicker">Statutory delinquency analysis</div>
                    <h2 class="pkg-h2">Ageing Summary</h2>
                </div>
                <span class="metric-pill">MSMED 45-day statutory cap anchor</span>
            </div>
            <p class="pkg-sub" style="margin-bottom:1rem;">Distribution across the five live ageing buckets in your workspace.</p>
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
                                    <span class="val num">{{ money_compact($bucket['amount']) }}</span>
                                </div>
                            </div>
                            <div class="lab">{{ $bucket['bucket']->value }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <aside class="pkg-card liquidity-card">
            <div>
                <div class="screen-kicker">TReDS auction floor</div>
                <h2 class="pkg-h2">Factoring Liquidity</h2>
                <p class="pkg-sub">Invoices already finance-ready in your workspace and what they could unlock on the exchange.</p>
            </div>

            <div class="liquidity-box">
                <div class="liquidity-row"><span>Eligible invoices</span><strong class="num">{{ money($readyAmount) }}</strong></div>
                <div class="liquidity-row"><span>Indicative discount rate</span><strong>{{ number_format($indicativeDiscountRate, 2) }}% p.a.</strong></div>
                <div class="liquidity-row liquidity-row--strong"><span>Instant payout available</span><strong class="num">{{ money($instantPayout) }}</strong></div>
            </div>

            <div class="liquidity-list">
                @forelse ($readyQueue->take(3) as $invoice)
                    <div class="liquidity-item">
                        <div>
                            <strong>{{ $invoice->number }}</strong>
                            <div class="pkg-muted">{{ $invoice->buyer?->name }}</div>
                        </div>
                        <strong class="num">{{ money($invoice->balance()) }}</strong>
                    </div>
                @empty
                    <p class="pkg-sub">No finance-ready invoices yet — complete evidence and confirm buyer onboarding.</p>
                @endforelse
            </div>

            <a class="pkg-btn pkg-btn--primary pkg-btn--block" href="{{ route('treds') }}">Access instant TReDS funds</a>
        </aside>
    </section>

    <section class="pkg-card">
        <div class="pkg-cardhead">
            <div>
                <div class="screen-kicker">Lifecycle &amp; exposure distribution</div>
                <h2 class="pkg-h2">Receivables Status Pipeline</h2>
            </div>
            <div class="pkg-muted">Aggregate portfolio: <strong class="num">{{ money($summary->total) }}</strong></div>
        </div>

        <div class="pipeline-board">
            @foreach ($summary->buckets as $bucket)
                <div class="pipeline-row">
                    <div class="pipeline-name">
                        <span class="pipeline-dot pipeline-dot--{{ $bucket['bucket']->barColor() }}"></span>
                        <span>{{ $bucket['bucket']->value }}</span>
                    </div>
                    <div class="pipeline-bar">
                        <span class="pipeline-fill pipeline-fill--{{ $bucket['bucket']->barColor() }}" style="width:{{ max(2, $bucket['percent']) }}%"></span>
                    </div>
                    <div class="pipeline-meta">
                        <span class="metric-pill">{{ plural($bucket['count'], 'invoice') }}</span>
                        <strong class="num">{{ money($bucket['amount']) }}</strong>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    <section class="pkg-card pkg-tablewrap">
        <div class="pkg-cardhead">
            <div>
                <div class="screen-kicker">Statutory receivables register</div>
                <h2 class="pkg-h2">Recent Invoices</h2>
            </div>
            <a class="pkg-btn pkg-btn--sm pkg-btn--ghost" href="{{ route('invoices.index') }}">View all invoices →</a>
        </div>

        @if ($recent->isEmpty())
            <div class="pkg-empty">
                <h2 class="pkg-h2">No invoices yet</h2>
                <p class="pkg-sub">Raise your first invoice to start the receivables pipeline.</p>
                <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.create') }}">+ Raise an invoice</a>
            </div>
        @else
            <table class="pkg-table ledger-table">
                <thead>
                <tr>
                    <th>Invoice</th>
                    <th>Buyer</th>
                    <th>Due Date</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($recent as $invoice)
                    <tr>
                        <td><a class="pkg-link" href="{{ route('invoices.show', $invoice) }}">{{ $invoice->number }}</a></td>
                        <td>{{ $invoice->buyer?->name }}</td>
                        <td class="{{ $invoice->overdueDays() > 0 ? 'pkg-neg' : '' }}">{{ $invoice->due_date?->format('d M Y') }}</td>
                        <td class="num {{ $invoice->overdueDays() > 0 ? 'pkg-neg' : '' }}">{{ money($invoice->balance()) }}</td>
                        <td><x-status-badge :status="$invoice->status" /></td>
                        <td>
                            <a class="pkg-btn pkg-btn--sm {{ $invoice->status === App\Enums\InvoiceStatus::Disputed ? 'pkg-btn--primary' : '' }}" href="{{ route('invoices.show', $invoice) }}">
                                {{ $invoice->status === App\Enums\InvoiceStatus::Disputed ? 'Start claim' : ($invoice->isFinanceReady() ? 'Finance this' : 'Review') }}
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</x-layouts.app>
