INVOICE {{ $invoice->number }} — {{ $businessName }}

Dear {{ $invoice->buyer?->name ?? 'Accounts payable' }},

Please find our invoice below. The figures are the ones recorded against it in
our books.

  Invoice number        {{ $invoice->number }}
  Raised on             {{ $invoice->invoice_date?->format('d M Y') ?? '—' }}
  Due on                {{ $invoice->due_date?->format('d M Y') ?? '—' }}
  Invoice value         {{ money($invoice->total_amount) }}
  Payments credited     {{ money($invoice->paidTotal()) }}
  Balance               {{ money($invoice->balance()) }}
@if ($invoice->interest() > 0)
  Interest accrued      {{ money($invoice->interest()) }} at {{ config('paykaro.interest_multiplier') }}x the {{ config('paykaro.bank_rate') }}% bank rate (Section 16, MSMED Act 2006)
  Total payable today   {{ money(round($invoice->balance() + $invoice->interest(), 2)) }}
@endif

Payment is due on {{ $invoice->due_date?->format('d M Y') ?? 'receipt' }}. After
that date the outstanding amount accrues statutory interest.

@if (($b = $invoice->business) && $b->bank_acc_no)
Remittance account
  {{ $b->bank_name ?: 'Bank not recorded' }}
  A/c {{ $b->bank_acc_no }}
  IFSC {{ $b->bank_ifsc ?: '—' }}
  GSTIN {{ $b->gstin ?: '—' }}
@endif

Please quote {{ $invoice->number }} with the remittance so it can be matched.

@if ($owner = $sender)
Regards,
{{ $owner->name }}
{{ $businessName }}
@endif
