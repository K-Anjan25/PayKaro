<x-layouts.app title="Claim packet" active="invoices">
    <x-page-header
        title="Claim evidence packet"
        :subtitle="$invoice->number.' · prepared for filing'"
    >
        <a class="pkg-btn pkg-btn--ghost" href="{{ route('invoices.show', $invoice) }}">← Invoice</a>
    </x-page-header>

    <div class="pkg-callout {{ $missing > 0 ? 'pkg-callout--coral' : '' }}">
        <strong>
            {{ $missing > 0
                ? plural($missing, 'item').' still missing from this packet'
                : 'Packet complete — everything a filing asks for is in here.' }}
        </strong>
        <small>
            Complete this checklist before filing. A time-bound claim is only as strong as the documents behind it,
            and the {{ config('paykaro.msme_due_days') }}-day interest clock is what you are claiming against.
        </small>
    </div>

    <div class="pkg-card pkg-tablewrap">
        <table class="pkg-table">
            <thead>
            <tr>
                <th style="width:14rem;">Item</th>
                <th>Value</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="{{ $row['missing'] ? 'pkg-neg' : '' }}">{{ $row['value'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="pkg-row" style="gap:.6rem;">
        <button class="pkg-btn pkg-btn--primary" type="button" onclick="window.print()">Print packet</button>
        <a class="pkg-btn" href="{{ route('invoices.show', $invoice) }}">Back to invoice</a>
    </div>
</x-layouts.app>
