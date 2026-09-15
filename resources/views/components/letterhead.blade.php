@props([
    'document' => 'Document',
    'number' => null,
    'issued' => null,
    'note' => null,
])

@php
    /*
     * The supplier's letterhead (BRAND_PLAN §4.4).
     *
     * An invoice and a claim packet are the customer's collateral, not ours: the
     * packet is filed with a forum, and the invoice is the thing a buyer's accounts
     * payable keeps. So the letterhead leads with *their* name, their GSTIN, their
     * registration and their bank — and PayKaro appears once, small, as the tool it
     * was prepared with.
     *
     * Before this component the claim packet opened with our wordmark and the line
     * "Receivables & liquidity infrastructure", which is our marketing on their
     * statutory filing.
     *
     * `$business` comes from the signed-in member's own workspace, so a packet can
     * only ever carry its own claimant's letterhead.
     */
    $business = $business ?? auth()->user()?->business;
    $member = $member ?? auth()->user();

    $identifiers = array_filter([
        $business?->gstin ? 'GSTIN '.$business->gstin : null,
        $business?->pan ? 'PAN '.$business->pan : null,
        $business?->udyam_no ? 'Udyam '.$business->udyam_no : null,
    ]);

    $bank = array_filter([$business?->bank_name, $business?->bank_acc_no, $business?->bank_ifsc]);
@endphp

<header {{ $attributes->merge(['class' => 'letterhead']) }}>
    <div class="letterhead-brand">
        <div class="letterhead-name">{{ $business?->name ?? config('app.name') }}</div>
        @if ($identifiers !== [])
            <div class="letterhead-ids">{{ implode(' · ', $identifiers) }}</div>
        @endif
        @if ($bank !== [])
            <div class="letterhead-bank">Remittance: {{ implode(' · ', $bank) }}</div>
        @endif
        @if ($member)
            <div class="letterhead-signatory">{{ $member->name }}{{ $member->role->label() ? ', '.$member->role->label() : '' }}</div>
        @endif
    </div>

    <div class="letterhead-document">
        <div class="letterhead-doc-label">{{ $document }}</div>
        @if ($number)
            <div class="letterhead-doc-number">{{ $number }}</div>
        @endif
        <div class="letterhead-doc-date">{{ ($issued ?? now())->format('d M Y') }}</div>
        @if ($note)
            <div class="letterhead-doc-note">{{ $note }}</div>
        @endif
    </div>
</header>
