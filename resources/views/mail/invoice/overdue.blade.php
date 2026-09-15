@extends('mail.layout')

@section('title', 'Invoice '.$invoice->number.' is '.$invoice->overdueDays().' days overdue')
@section('preheader', money($totalPayable).' now payable on '.$businessName.' invoice '.$invoice->number)

@section('content')
    @php
        $opening = match ($escalation) {
            'statutory' => 'This invoice is now more than a month past its due date and is accruing statutory interest. We would rather settle it between us than file it.',
            'firm' => 'This invoice is two weeks past its due date. Please confirm the payment date.',
            default => 'This is a courtesy reminder that the invoice below has fallen due.',
        };
    @endphp

    <p style="margin:0 0 4px; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:12px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:#dc2626;">
        {{ $invoice->overdueDays() }} day{{ $invoice->overdueDays() === 1 ? '' : 's' }} overdue
    </p>
    <h1 style="margin:0 0 10px; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:20px; font-weight:800; letter-spacing:-.03em; color:#0b132b;">
        Invoice {{ $invoice->number }} — {{ money($totalPayable) }} now payable
    </h1>
    <p style="margin:0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:14px; line-height:1.65; color:#334155;">
        {{ $opening }}
    </p>

    @include('mail.invoice.facts', ['invoice' => $invoice])

    <p style="margin:20px 0 0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; line-height:1.7; color:#334155;">
        The interest line is computed from the invoice date and the due date on record — it is not a figure we choose, and it is the same one our claim packet will quote.
        @if (($b = $invoice->business) && $b->bank_acc_no)
            Payment to the remittance account below settles it.
        @endif
    </p>

    @if ($owner = $sender)
        <p style="margin:20px 0 0; font-family:'Plus Jakarta Sans',Helvetica,Arial,sans-serif; font-size:13px; line-height:1.7; color:#334155;">
            If payment has already been made, or a document we sent is missing, reply to this message and we will correct the record.<br><br>
            <strong style="color:#0b132b;">{{ $owner->name }}</strong><br>
            {{ $businessName }}
        </p>
    @endif
@endsection

@section('footer')
    Interest accrues daily at {{ config('paykaro.interest_multiplier') }}× the prevailing {{ config('paykaro.bank_rate') }}% bank rate until the balance is cleared.
@endsection
