{{ strtoupper($escalation) }} REMINDER — INVOICE {{ $invoice->number }} IS {{ $invoice->overdueDays() }} DAYS OVERDUE

{{ money($totalPayable) }} is payable today to settle this invoice.

  Invoice number        {{ $invoice->number }}
  Raised on             {{ $invoice->invoice_date?->format('d M Y') ?? '—' }}
  Due on                {{ $invoice->due_date?->format('d M Y') ?? '—' }}
  Invoice value         {{ money($invoice->total_amount) }}
  Payments credited     {{ money($invoice->paidTotal()) }}
  Balance               {{ money($invoice->balance()) }}
  Interest accrued      {{ money($invoice->interest()) }} ({{ config('paykaro.interest_multiplier') }}x bank rate, Section 16 MSMED Act 2006), to {{ $asOf->format('d M Y') }}
  Total payable today   {{ money($totalPayable) }}

Interest accrues daily until the balance is cleared. It is computed from the
invoice and due dates on record — the same figure our claim packet quotes.

@if (($b = $invoice->business) && $b->bank_acc_no)
Remittance account
  {{ $b->bank_name ?: 'Bank not recorded' }}
  A/c {{ $b->bank_acc_no }}
  IFSC {{ $b->bank_ifsc ?: '—' }}
@endif

If payment has already been made, or a document we sent is missing, reply and we
will correct the record.

@if ($owner = $sender)
{{ $owner->name }}
{{ $businessName }}
@endif
