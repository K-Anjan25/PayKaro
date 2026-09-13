@php $search = (string) request('q', ''); @endphp

<x-layouts.app title="Invoices" active="invoices">
    <x-page-header title="Invoices" subtitle="Every invoice, one pipeline.">
        <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.create') }}">+ New Invoice</a>
    </x-page-header>

    @php
        $tabs = ['all' => 'All'];
        foreach (App\Enums\InvoiceStatus::cases() as $tabStatus) {
            if ($tabStatus !== App\Enums\InvoiceStatus::Draft) {
                $tabs[$tabStatus->value] = $tabStatus->label();
            }
        }
    @endphp

    <div class="pkg-tabs">
        @foreach ($tabs as $key => $label)
            <a class="pkg-tab {{ $status === $key ? 'is-active' : '' }}"
               href="{{ route('invoices.index', array_filter(['status' => $key === 'all' ? null : $key, 'q' => $search])) }}">{{ $label }}</a>
        @endforeach
    </div>

    @if ($invoices->isEmpty())
        <div class="pkg-card pkg-empty">
            <h2 class="pkg-h2">{{ $status === 'all' ? 'No invoices yet' : 'Nothing in this state' }}</h2>
            <p class="pkg-sub">
                @if ($search !== '')
                    Nothing matched “{{ $search }}”.
                @elseif ($status === 'all')
                    Raise your first invoice to start tracking receivables.
                @else
                    No invoices are currently {{ strtolower($tabs[$status] ?? $status) }}.
                @endif
            </p>
            <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.create') }}">+ Raise an invoice</a>
        </div>
    @else
        <div class="pkg-card pkg-tablewrap">
            <div class="pkg-cardhead">
                <h2 class="pkg-h2">{{ $tabs[$status] ?? 'All' }} invoices</h2>
                <span class="pkg-filter" style="padding:.35rem .6rem;">
                    @if ($search !== '')
                        “{{ $search }}” · {{ plural($invoices->total(), 'match') }}
                        <a class="pkg-link" href="{{ route('invoices.index', array_filter(['status' => $status === 'all' ? null : $status])) }}">Clear</a>
                    @else
                        {{ plural($invoices->total(), 'result') }}
                    @endif
                </span>
            </div>

            <table class="pkg-table">
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
                            <a class="pkg-link" href="{{ route('invoices.show', $invoice) }}"><strong>{{ $invoice->number }}</strong></a>
                            <div class="pkg-muted">{{ $invoice->invoice_date?->format('d M Y') }}</div>
                        </td>
                        <td>{{ $invoice->buyer?->name }}</td>
                        <td>
                            {{ $invoice->due_date?->format('d M Y') }}
                            @if ($invoice->overdueDays() > 0)
                                <div class="pkg-muted pkg-neg">+{{ $invoice->overdueDays() }}d</div>
                            @endif
                        </td>
                        <td>
                            <strong class="num">{{ money($invoice->balance()) }}</strong>
                            @if ($invoice->interest() > 0)
                                <div class="pkg-muted pkg-neg">+ {{ money($invoice->interest()) }}</div>
                            @endif
                        </td>
                        <td><x-health-badge :health="$invoice->health()" /></td>
                        <td><x-status-badge :status="$invoice->status" /></td>
                        <td><x-progress :score="$invoice->readiness()" :show-label="true" /></td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            {{ $invoices->links('components.pagination', ['noun' => 'invoices']) }}
        </div>
    @endif
</x-layouts.app>
