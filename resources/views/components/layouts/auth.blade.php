@props(['title', 'copy' => null])

@php
    $defaultCopy = 'Turn invoice mess into finance-ready receivables.';
    $stats = [
        ['₹240Cr+', 'Invoices cleared'],
        ['<48 Hrs', 'Disbursal speed'],
        ['99.8%', 'Reconciliation rate'],
    ];
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
        'metaDescription' => 'Sign in to '.config('app.name').': '.config('paykaro.tagline').'.',
    ])
    @include('partials.datepicker')
</head>
<body class="page" style="margin:0;">
<div class="auth">
    <div class="auth-side">
        <div>
            <div class="metric-pill metric-pill--soft" style="display:inline-flex; margin-bottom:2rem; background:rgba(255,255,255,.12); color:#fff; border-color:rgba(255,255,255,.15);">MSME invoice &amp; receivables tracker</div>
            <a href="{{ route('landing') }}" class="auth-brand" aria-label="Home">
                <div>
                    <div class="auth-name"><x-brand-wordmark /></div>
                    <div class="auth-tag">{{ config('paykaro.tagline') }}</div>
                </div>
            </a>
        </div>

        <div>
            <div class="auth-headline">Turn invoice mess into finance-ready receivables.</div>
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

        <div class="auth-foot">© {{ now()->year }} {{ config('app.name') }} Technologies Pvt. Ltd. · Enterprise security · RBI regulated entities</div>
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
