@props(['health'])

{{--
    Paid / Due Soon / Overdue (bucket) — the one-glance column in the reference
    table. Derived by HealthStatus::for(), never stored.
--}}
<span {{ $attributes->merge(['class' => 'pkg-badge pkg-badge--'.$health->tone()]) }}>{{ $health->label() }}</span>
