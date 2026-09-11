@props(['score', 'showLabel' => true])

@php
    $threshold = (int) config('paykaro.finance_ready_score');
    $tone = $score >= $threshold ? 'success' : ($score >= 60 ? 'info' : 'danger');
    $word = $score >= $threshold ? 'Ready' : ($score >= 60 ? 'Partial' : 'Gaps');
@endphp

{{-- Readiness bar. "Ready" is graded against config('paykaro.finance_ready_score'),
     not a literal 85 baked into the markup. --}}
<span {{ $attributes->merge(['class' => 'pkg-progress']) }}>
    <span class="pkg-progress-bar pkg-progress-bar--{{ $tone }}" style="width:{{ $score }}%"></span>
    @if ($showLabel)
        <span class="pkg-progress-label">{{ $word }} · {{ $score }}</span>
    @endif
</span>
