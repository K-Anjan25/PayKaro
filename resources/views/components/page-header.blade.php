@props(['title', 'subtitle' => null])

<div class="pkg-pagehead">
    <div>
        <h1 class="pkg-h1">{{ $title }}</h1>
        @if ($subtitle)
            <p class="pkg-sub">{{ $subtitle }}</p>
        @endif
    </div>
    @if (! $slot->isEmpty())
        <div class="pkg-pagehead-actions">{{ $slot }}</div>
    @endif
</div>
