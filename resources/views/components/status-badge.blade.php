@props(['status'])

{{--
    Pipeline status badge. Tone and label come from InvoiceStatus, so a new
    status cannot render as an unstyled string.
--}}
<span {{ $attributes->merge(['class' => 'pkg-badge pkg-badge--'.$status->tone()]) }}>{{ $status->label() }}</span>
