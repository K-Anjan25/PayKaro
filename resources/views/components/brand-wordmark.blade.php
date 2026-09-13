{{--
    Two-tone wordmark: "Pay" in ink, "Karo" in deep blue. Split from the app
    name at the camelCase boundary so a renamed deployment stays correct.
--}}
@php
    $parts = preg_split('/(?<=[a-z])(?=[A-Z])/', config('app.name'));
@endphp

@if (is_array($parts) && count($parts) === 2)
    <span>{{ $parts[0] }}</span><span class="b">{{ $parts[1] }}</span>
@else
    {{ config('app.name') }}
@endif
