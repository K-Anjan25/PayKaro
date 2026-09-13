@props(['class' => 'pkg-brand-logo', 'size' => 34])

{{--
    PayKaro's mark: a deep-blue rounded tile with a warm-gold ₹.
    The only brand asset in the app, so it is a component rather than the
    `logoMark()` function the flat-PHP router echoed in eight places.
--}}
<span {{ $attributes->merge(['class' => $class]) }}>
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 34 34" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <rect width="34" height="34" rx="9" fill="#0b3b7a" />
        <path d="M20.5 8h-7.6v4h7.6a2.5 2.5 0 0 1 0 5h-7.6" stroke="#f5a623" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
        <path d="M12.9 12v14M16.3 21h5.6M12.9 21h5.6" stroke="#f5a623" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
    </svg>
</span>
