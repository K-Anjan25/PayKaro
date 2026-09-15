{{--
    One invoice summary, shared by every message in this folder.

    Every figure is read off the invoice the app stores — the due date it derived,
    the balance after recorded payments, the interest the statute accrues — so the
    email cannot quote a number the workspace disagrees with. That coherence is the
    point of the whole layer, and it is what InvoiceMailTest asserts.
--}}
@php
    $rows = [
        ['Invoice number', $invoice->number],
        ['Raised on', $invoice->invoice_date?->format('d M Y') ?? '—'],
        ['Due on', ($invoice->due_date?->format('d M Y') ?? '—').' ('.$invoice->status->label().')'],
        ['Invoice value', money($invoice->total_amount)],
        ['Payments credited', money($invoice->paidTotal())],
        ['Balance', money($invoice->balance())],
    ];

    if ($invoice->interest() > 0) {
        $rows[] = [
            'Interest accrued',
            money($invoice->interest()).' at '.config('paykaro.interest_multiplier').'× the '.config('paykaro.bank_rate').'% bank rate, under Section 16 of the MSMED Act',
        ];
        $rows[] = ['Total payable today', money(round($invoice->balance() + $invoice->interest(), 2))];
    }
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 0; border-collapse:collapse;">
    @foreach ($rows as $i => [$label, $value])
        <tr>
            <td style="padding:9px 0; border-top:1px solid #e2e8f0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; color:#64748b; width:42%; vertical-align:top;">{{ $label }}</td>
            <td style="padding:9px 0; border-top:1px solid #e2e8f0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; font-weight:700; color:{{ $label === 'Total payable today' ? '#dc2626' : '#0b132b' }}; text-align:right; vertical-align:top;">{{ $value }}</td>
        </tr>
    @endforeach
</table>
