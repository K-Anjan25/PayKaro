@props([
    'label',
    'value',
    'trend' => null,
    'tone' => 'blue',
    'trendDirection' => 'up',
])

@php
    $arrow = match ($trendDirection) {
        'none' => '',
        'down' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>',
        default => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>',
    };
    $icon = $slot->isEmpty()
        ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>'
        : $slot;
@endphp

<div {{ $attributes->merge(['class' => 'pkg-card pkg-kpi']) }}>
    <div class="pkg-kpi-head">
        {{-- The icon is markup (the default is an inline SVG string), so it must
             not go through the escaping echo. --}}
        <div class="pkg-kpi-ico pkg-kpi-ico--{{ $tone }}">{!! $icon !!}</div>
        <div class="pkg-kpi-label">{{ $label }}</div>
    </div>
    <div class="pkg-kpi-value num">{{ $value }}</div>
    @if ($trend)
        <div class="pkg-kpi-trend">
            @if ($arrow)<span class="{{ $trendDirection }}">{!! $arrow !!}</span>@endif
            {{ $trend }}
        </div>
    @else
        <div class="pkg-kpi-trend" style="visibility:hidden">&nbsp;</div>
    @endif
</div>
