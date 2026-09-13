@props(['status'])

@php
    $pipeline = App\Enums\InvoiceStatus::pipeline();
    $disputed = $status === App\Enums\InvoiceStatus::Disputed;
    $current = $disputed ? null : array_search($status, $pipeline, true);
@endphp

{{--
    raised → accepted → financed → settled, with `disputed` shown only when that
    is the current state: a disputed invoice has left the happy path, and drawing
    it still on the line to settlement would be misleading.
--}}
<div {{ $attributes->merge(['class' => 'pkg-timeline']) }}>
    @foreach ($pipeline as $position => $step)
        @php
            $class = match (true) {
                $current === false, $current === null => '',
                $position === $current => 'is-now',
                $position < $current => 'is-done',
                default => '',
            };
        @endphp
        <div class="pkg-tl-step {{ $class }}">
            <span class="pkg-tl-dot"></span>
            <span class="pkg-tl-label">{{ $step->label() }}</span>
        </div>
    @endforeach

    @if ($disputed)
        <div class="pkg-tl-step is-warn">
            <span class="pkg-tl-dot"></span>
            <span class="pkg-tl-label">{{ $status->label() }}</span>
        </div>
    @endif
</div>
