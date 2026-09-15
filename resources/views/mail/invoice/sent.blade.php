@extends('mail.layout')

@section('title', 'Invoice '.$invoice->number.' from '.$businessName)
@section('preheader', 'Invoice '.$invoice->number.' — '.money($invoice->balance()).' due '.($invoice->due_date?->format('d M Y') ?? 'on receipt'))

@section('content')
    <h1 style="margin:0 0 6px; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:20px; font-weight:800; letter-spacing:-.03em; color:#0b132b;">
        Invoice {{ $invoice->number }}
    </h1>
    <p style="margin:0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:14px; line-height:1.65; color:#334155;">
        Dear {{ $invoice->buyer?->name ?? 'Accounts payable' }}, please find {{ $businessName }}'s invoice {{ $invoice->number }} below.
    </p>

    @include('mail.invoice.facts', ['invoice' => $invoice])

    <p style="margin:20px 0 0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; line-height:1.7; color:#334155;">
        Payment is due on <strong>{{ $invoice->due_date?->format('d M Y') ?? 'receipt' }}</strong>.
        @if ($invoice->due_date)
            After that date the amount outstanding accrues interest at the statutory rate under Section 16 of the MSMED Act, 2006.
        @endif
        Quote <strong>{{ $invoice->number }}</strong> with the remittance so it can be matched to this invoice.
    </p>

    @if ($owner = $sender)
        <p style="margin:20px 0 0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; line-height:1.7; color:#334155;">
            Regards,<br>
            <strong style="color:#0b132b;">{{ $owner->name }}</strong><br>
            {{ $businessName }}
        </p>
    @endif
@endsection

@section('footer')
    You are receiving this because {{ $businessName }} raised an invoice against your account.
@endsection
