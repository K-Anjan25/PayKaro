<x-layouts.app title="Finance queue" active="treds">
    <x-page-header
        title="Finance queue"
        subtitle="Invoices that could be financed today, and the gaps holding the rest back."
    >
        <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.create') }}">+ Raise an invoice</a>
    </x-page-header>

    @if ($queue->isEmpty())
        <div class="pkg-card pkg-empty">
            <h2 class="pkg-h2">Nothing in the queue</h2>
            <p class="pkg-sub">An invoice enters the queue once it is raised and not yet settled or disputed.</p>
            <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.create') }}">+ Raise an invoice</a>
        </div>
    @else
        <div class="pkg-grid pkg-grid--4">
            <x-kpi-card label="Finance-ready" :value="$ready->count().' of '.$queue->count()"
                        :trend="money($disbursable).' can be disbursed today'"
                        trend-direction="none" tone="blue" />
            <x-kpi-card label="Ready amount" :value="money($disbursable)"
                        :trend="'needs '.config('paykaro.finance_ready_score').'/100 readiness'"
                        trend-direction="none" tone="gold" />
        </div>

        <div class="pkg-grid pkg-grid--3">
            @foreach ($ready as $invoice)
                <div class="pkg-card pkg-queue pkg-queue--ready">
                    <div class="pkg-queue-top">
                        <strong>{{ $invoice->number }}</strong>
                        <x-treds-badge :treds="$invoice->treds()" />
                    </div>
                    <div class="pkg-muted">{{ $invoice->buyer?->name }} · {{ $invoice->due_date?->format('d M Y') }}</div>
                    <div class="pkg-queue-amt num">{{ money($invoice->balance()) }}</div>
                    <x-progress :score="$invoice->readiness()" />
                    <a class="pkg-btn pkg-btn--sm pkg-btn--primary" href="{{ route('invoices.show', $invoice) }}">Finance it</a>
                </div>
            @endforeach

            @foreach ($blocked as $invoice)
                <div class="pkg-card pkg-queue">
                    <div class="pkg-queue-top">
                        <strong>{{ $invoice->number }}</strong>
                        <x-treds-badge :treds="$invoice->treds()" />
                    </div>
                    <div class="pkg-muted">{{ $invoice->buyer?->name }} · {{ $invoice->due_date?->format('d M Y') }}</div>
                    <div class="pkg-queue-amt num">{{ money($invoice->balance()) }}</div>
                    <x-progress :score="$invoice->readiness()" />
                    <p class="pkg-sub" style="margin:.5rem 0 0;">
                        {{ $invoice->treds() === App\Enums\TredsStatus::PendingBuyerOnboard
                            ? 'Buyer is not on TReDS yet — confirm their onboarding.'
                            : 'Missing evidence — open it to complete the checklist.' }}
                        <a class="pkg-link" href="{{ route('invoices.show', $invoice) }}">Open</a>
                    </p>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
