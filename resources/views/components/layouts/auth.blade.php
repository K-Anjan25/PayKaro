@props(['title', 'copy' => null])

@php
    // Supporting copy, not a second tagline: BRAND_PLAN §1.1. The brand line itself
    // is `paykaro.headline`, rendered below and nowhere else re-typed.
    $defaultCopy = 'Every invoice keeps its dated evidence and its statutory interest, so a delayed payment or a TReDS claim has something to stand on.';
    // BRAND_PLAN §4: these were ₹240Cr+ cleared, "<48 Hrs disbursal speed" and a
    // 99.8% reconciliation rate — none of it measured by anything. They are now the
    // same provable claims the landing page publishes.
    $stats = array_map(
        fn (array $claim) => [$claim['value'], $claim['label']],
        App\Support\Proof::highlights(),
    );
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    @include('partials.brand-meta', [
        'metaTitle' => $title.' — '.config('app.name'),
        'metaDescription' => 'Sign in to '.config('app.name').': '.config('paykaro.descriptor').'.',
    ])
    @include('partials.datepicker')
</head>
<body class="page" style="margin:0;">
<div class="auth">
    <div class="auth-side">
        <div>
            <div class="metric-pill metric-pill--soft" style="display:inline-flex; margin-bottom:2rem; background:rgba(255,255,255,.12); color:#fff; border-color:rgba(255,255,255,.15);">{{ config('paykaro.descriptor') }}</div>
            <a href="{{ route('landing') }}" class="auth-brand" aria-label="Home">
                <div>
                    <div class="auth-name"><x-brand-wordmark /></div>
                    <div class="auth-tag">{{ config('paykaro.descriptor') }}</div>
                </div>
            </a>
        </div>

        <div>
            <div class="auth-headline">{{ config('paykaro.headline') }}</div>
            <p class="auth-copy">{{ $copy ?? $defaultCopy }}</p>

            <div class="auth-statgrid">
                @foreach ($stats as [$value, $label])
                    <div class="auth-stat">
                        <strong>{{ $value }}</strong>
                        <span>{{ $label }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Was "Enterprise security · RBI regulated entities": PayKaro is not an
             RBI-regulated entity, and the security page is scrupulous about what the
             product does not do. Trust language a visitor cannot check is the same
             problem as a traction figure they cannot check (BRAND_PLAN §4). --}}
        <div class="auth-foot">© {{ now()->year }} {{ config('app.name') }} · {{ config('paykaro.descriptor') }}</div>
    </div>

    <div class="auth-form">
        <div class="auth-wrap auth-wrap--card">
            {{ $slot }}
        </div>
    </div>
</div>
@include('partials.theme-toggle')
</body>
</html>
