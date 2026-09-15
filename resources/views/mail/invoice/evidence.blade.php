@extends('mail.layout')

@section('title', 'Documents pending for invoice '.$invoice->number)
@section('preheader', plural($missing->count(), 'document').' pending before invoice '.$invoice->number.' can be financed')

@section('content')
    <h1 style="margin:0 0 10px; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:20px; font-weight:800; letter-spacing:-.03em; color:#0b132b;">
        {{ plural($missing->count(), 'document') }} pending for invoice {{ $invoice->number }}
    </h1>
    <p style="margin:0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:14px; line-height:1.65; color:#334155;">
        Dear {{ $invoice->buyer?->name ?? 'Accounts payable' }}, we are completing the document trail for invoice {{ $invoice->number }} ({{ money($invoice->balance()) }} outstanding).
        These items are still open on our side:
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:18px 0 0; border-collapse:collapse;">
        @foreach ($missing as $i => $label)
            <tr>
                <td style="padding:11px 0; border-top:1px solid #e2e8f0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; color:#0b132b; font-weight:700;">
                    {{ $loop->iteration }}. {{ $label }}
                </td>
            </tr>
        @endforeach
    </table>

    <p style="margin:20px 0 0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; line-height:1.7; color:#334155;">
        Dated proof of delivery is what lets this invoice be discounted on TReDS, and what a delayed-payment claim under the MSMED Act stands on. A copy by reply is enough — we will attach it to the invoice record.
    </p>

    @if ($owner = $sender)
        <p style="margin:20px 0 0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; line-height:1.7; color:#334155;">
            <strong style="color:#0b132b;">{{ $owner->name }}</strong><br>
            {{ $businessName }}
        </p>
    @endif
@endsection

@section('footer')
    Invoice {{ $invoice->number }} · {{ money($invoice->balance()) }} outstanding · due {{ $invoice->due_date?->format('d M Y') ?? '—' }}
@endsection
