@php
    $latestDispute = $invoice->disputes->first();
    $claimTotal = round($invoice->balance() + $invoice->interest(), 2);
@endphp

<x-layouts.app title="Claim packet" active="invoices">
    <div class="claim-actions">
        <div>
            <div class="screen-kicker">Statutory conciliation dossier</div>
            <h1 class="pkg-h1">Claim Packet</h1>
            <p class="pkg-sub">Printable evidence packet for filing and internal review.</p>
        </div>
        <div class="pkg-row" style="gap:.6rem; flex-wrap:wrap;">
            <button class="pkg-btn pkg-btn--primary" type="button" onclick="window.print()">Print packet</button>
            <a class="pkg-btn" href="{{ route('invoices.show', $invoice) }}">Back to invoice</a>
        </div>
    </div>

    <article class="claim-sheet">
        <div class="claim-sheet-head">
            <div>
                <div class="app-brand-word" style="font-size:1.6rem;"><x-brand-wordmark /></div>
                <div class="pkg-muted">Receivables &amp; liquidity infrastructure</div>
            </div>
            <div class="claim-sheet-meta">
                <span class="metric-pill">Confidential / judicial record</span>
                <strong>{{ $latestDispute?->forum->label() ?? 'Draft claim packet' }}</strong>
                <span>Date of issue: {{ now()->format('d M Y') }}</span>
            </div>
        </div>

        <div class="claim-highlight {{ $missing > 0 ? 'is-danger' : '' }}">
            <strong>{{ $missing > 0 ? plural($missing, 'required item').' missing from the packet' : 'Verified statutory claim enclosure — packet complete' }}</strong>
            <p>The due window elapsed on {{ $invoice->due_date?->format('d M Y') ?? '—' }}. Interest is computed from the invoice and due dates already stored in the ledger.</p>
        </div>

        <section class="claim-grid claim-grid--2">
            <div class="claim-box">
                <div class="claim-box-title">Claimant (supplier / MSME)</div>
                <h2>{{ auth()->user()->business?->name ?? 'Business' }}</h2>
                <p>Udyam: {{ auth()->user()->business?->udyam_no ?: 'Not recorded' }}</p>
                <p>GSTIN: {{ auth()->user()->business?->gstin ?: 'Not recorded' }}</p>
                <p>Managing director: {{ auth()->user()->name }}</p>
                <p>Bank / IFSC: {{ auth()->user()->business?->bank_name ?: '—' }} · {{ auth()->user()->business?->bank_ifsc ?: '—' }}</p>
            </div>
            <div class="claim-box">
                <div class="claim-box-title">Respondent (buyer)</div>
                <h2>{{ $invoice->buyer?->name ?? '—' }}</h2>
                <p>Entity type: {{ $invoice->buyer?->type->label() ?? 'Unknown' }}</p>
                <p>GSTIN: {{ $invoice->buyer?->gstin ?: 'Not on record' }}</p>
                <p>Invoice: {{ $invoice->number }}</p>
                <p>Due date: {{ $invoice->due_date?->format('d M Y') ?? '—' }}</p>
            </div>
        </section>

        <section class="claim-table-wrap">
            <div class="claim-section-head">
                <strong>Financial schedule &amp; outstanding balance ledger</strong>
                <span>Currency: Indian Rupees (INR, ₹)</span>
            </div>
            <table class="claim-table">
                <thead>
                <tr>
                    <th>Particulars</th>
                    <th>Basis</th>
                    <th>Date</th>
                    <th>Amount (₹)</th>
                </tr>
                </thead>
                <tbody>
                <tr>
                    <td>Principal tax invoice value</td>
                    <td>Invoice total</td>
                    <td>{{ $invoice->invoice_date?->format('d M Y') ?? '—' }}</td>
                    <td class="num">{{ money($invoice->total_amount) }}</td>
                </tr>
                <tr>
                    <td>Recorded payments credited</td>
                    <td>Settlement ledger</td>
                    <td>{{ $invoice->payments->first()?->paid_on?->format('d M Y') ?? '—' }}</td>
                    <td class="num">{{ money($invoice->paidTotal()) }}</td>
                </tr>
                <tr>
                    <td>Principal overdue balance</td>
                    <td>Open receivable</td>
                    <td>{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                    <td class="num">{{ money($invoice->balance()) }}</td>
                </tr>
                <tr>
                    <td>{{ $rows[6]['label'] ?? 'Interest due' }}</td>
                    <td>Section 16 computation</td>
                    <td>Accrued to {{ now()->format('d M Y') }}</td>
                    <td class="num">{{ money($invoice->interest()) }}</td>
                </tr>
                <tr class="claim-total-row">
                    <td colspan="3">Total statutory claim</td>
                    <td class="num">{{ money($claimTotal) }}</td>
                </tr>
                </tbody>
            </table>
        </section>

        <section class="claim-annexures">
            <div class="claim-section-head">
                <strong>Statutory evidence annexures &amp; verification trail</strong>
                <span>All facts drawn from the live invoice record</span>
            </div>
            <div class="claim-grid claim-grid--2">
                @foreach ($rows as $row)
                    <div class="claim-annexure {{ $row['missing'] ? 'is-danger' : '' }}">
                        <strong>{{ $row['label'] }}</strong>
                        <p>{{ $row['value'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    </article>

    {{-- Printed on every page (fixed elements repeat in print): the two strings a
         filing is cited by. Hidden on screen by `.claim-print-foot`. --}}
    <footer class="claim-print-foot">
        <span>{{ auth()->user()->business?->name ?? 'Claimant' }} · GSTIN {{ auth()->user()->business?->gstin ?: 'not recorded' }}</span>
        <span>Claim packet · Invoice {{ $invoice->number }} · printed {{ now()->format('d M Y') }}</span>
    </footer>
</x-layouts.app>
