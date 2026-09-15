{{ strtoupper(plural($missing->count(), 'document')) }} PENDING — INVOICE {{ $invoice->number }}

Dear {{ $invoice->buyer?->name ?? 'Accounts payable' }},

We are completing the document trail for invoice {{ $invoice->number }}
({{ money($invoice->balance()) }} outstanding, due {{ $invoice->due_date?->format('d M Y') ?? '—' }}).
These items are still open on our side:

@foreach ($missing as $label)
  {{ $loop->iteration }}. {{ $label }}
@endforeach

Dated proof of delivery is what lets this invoice be discounted on TReDS, and
what a delayed-payment claim under the MSMED Act stands on. A copy by reply is
enough — we will attach it to the invoice record.

@if ($owner = $sender)
{{ $owner->name }}
{{ $businessName }}
@endif
