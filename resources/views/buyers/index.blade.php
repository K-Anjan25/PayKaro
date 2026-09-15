@php
    $totalOutstanding = round($buyers->sum(fn ($buyer) => (float) ($outstanding[$buyer->id] ?? 0)), 2);
    $onboardedOutstanding = round($buyers->filter(fn ($buyer) => $buyer->treds_onboarded->isOnboarded())->sum(fn ($buyer) => (float) ($outstanding[$buyer->id] ?? 0)), 2);
    $coverage = $totalOutstanding > 0 ? round($onboardedOutstanding / $totalOutstanding * 100, 1) : 0;
    $sovereignExposure = round($buyers->filter(fn ($buyer) => in_array($buyer->type->value, ['cpse', 'psu'], true))->sum(fn ($buyer) => (float) ($outstanding[$buyer->id] ?? 0)), 2);
@endphp

<x-layouts.app title="Buyers" active="buyers">
    <section class="metric-grid metric-grid--3">
        <article class="metric-card metric-card--dark">
            <div class="metric-eyebrow">Total outstanding</div>
            <div class="metric-value num">{{ money($totalOutstanding) }}</div>
            <div class="metric-foot">Across {{ plural($buyers->count(), 'buyer') }}</div>
        </article>
        <article class="metric-card metric-card--brand">
            <div class="metric-eyebrow">TReDS coverage</div>
            <div class="metric-value num">{{ number_format($coverage, 1) }}%</div>
            <div class="metric-foot">{{ plural($buyers->filter(fn ($buyer) => $buyer->treds_onboarded->isOnboarded())->count(), 'counterparty') }} onboarded</div>
        </article>
        <article class="metric-card metric-card--soft">
            <div class="metric-eyebrow">Sovereign / CPSE exposure</div>
            <div class="metric-value num">{{ money($sovereignExposure) }}</div>
            <div class="metric-foot">Buyer records are create-only</div>
        </article>
    </section>

    <div class="screen-head">
        <div>
            <h1 class="pkg-h1">Buyers</h1>
            <p class="pkg-sub">Your customer directory, GSTIN references and TReDS onboarding status in one ledger.</p>
        </div>
        @can('create', App\Models\Buyer::class)
            <a class="pkg-btn pkg-btn--primary" href="{{ route('buyers.create') }}">+ Add buyer</a>
        @endcan
    </div>

    @if ($buyers->isEmpty())
        <div class="pkg-card pkg-empty">
            <h2 class="pkg-h2">No buyers yet.</h2>
            <p class="pkg-sub">Create your first buyer to start raising invoices.</p>
            @can('create', App\Models\Buyer::class)
                <a class="pkg-btn pkg-btn--primary" href="{{ route('buyers.create') }}">+ Add buyer</a>
            @endcan
        </div>
    @else
        <section class="pkg-card buyers-board">
            <div class="buyers-toolbar">
                <label class="buyers-filter">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                    <input id="buyer-filter" type="search" placeholder="Filter by buyer name or GSTIN...">
                </label>
                <div class="buyers-legend">
                    <span><span class="buyers-legend-dot is-blue"></span> TReDS onboarded</span>
                    <span><span class="buyers-legend-dot"></span> Pending / unknown</span>
                </div>
            </div>

            <div class="pkg-tablewrap" style="padding:0; margin-bottom:0;">
                <table class="pkg-table ledger-table" id="buyers-table">
                    <thead>
                    <tr>
                        <th>Buyer</th>
                        <th>GSTIN</th>
                        <th>Type</th>
                        <th>TReDS</th>
                        <th>Outstanding</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($buyers as $buyer)
                        <tr data-filter="{{ strtolower($buyer->name.' '.$buyer->gstin) }}">
                            <td>
                                {{-- The row links to the buyer's own page, which is what
                                     makes the outstanding figure answerable. --}}
                                <a class="buyer-cell" href="{{ route('buyers.show', $buyer) }}">
                                    <span class="buyer-avatar">{{ strtoupper(mb_substr($buyer->name, 0, 1)) }}</span>
                                    <div>
                                        <strong>{{ $buyer->name }}</strong>
                                        <div class="pkg-muted">{{ $buyer->type->label() }} buyer</div>
                                    </div>
                                </a>
                            </td>
                            <td class="num">{{ $buyer->gstin ?: '—' }}</td>
                            <td><span class="metric-pill metric-pill--soft">{{ $buyer->type->label() }}</span></td>
                            <td><x-treds-badge :treds="$buyer->treds_onboarded" /></td>
                            <td><strong class="num">{{ money($outstanding[$buyer->id] ?? 0) }}</strong></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="board-foot">
                <span>Immutable ledger: buyer records are locked for statutory MSMED compliance.</span>
                <span>Displaying {{ plural($buyers->count(), 'institutional account') }} · <strong class="num">Sum: {{ money($totalOutstanding) }}</strong></span>
            </div>
        </section>

        <script>
            (function () {
                const input = document.getElementById('buyer-filter');
                const rows = document.querySelectorAll('#buyers-table tbody tr');

                input.addEventListener('input', function () {
                    const query = input.value.trim().toLowerCase();
                    rows.forEach((row) => {
                        row.style.display = !query || row.dataset.filter.includes(query) ? '' : 'none';
                    });
                });
            })();
        </script>
    @endif
</x-layouts.app>
