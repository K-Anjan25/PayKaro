@props(['title', 'copy' => null])

@php
    $chips = [
        ['lock', 'Multi-tenant secured'],
        ['treds', 'TReDS-ready'],
        ['clock', '45-day due window'],
    ];

    $defaultCopy = 'One pipeline for every invoice — evidence complete, interest computed, '
        .'and ready to finance or claim the moment it\'s overdue.';
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="page" style="margin:0;">
<div class="auth">
    <div class="auth-side">
        <div style="position:relative;z-index:1;">
            <a href="{{ route('landing') }}" class="auth-brand" aria-label="Home">
                <x-logo-mark />
                <div>
                    <div class="auth-name"><x-brand-wordmark /></div>
                    <div class="auth-tag">{{ config('paykaro.tagline') }}</div>
                </div>
            </a>
        </div>
        <div style="position:relative;z-index:1;">
            <div class="auth-headline">Turn invoice mess into<br>finance-ready receivables.</div>
            <p class="auth-copy">{{ $copy ?? $defaultCopy }}</p>
            <div class="auth-chips">
                @foreach ($chips as [$icon, $label])
                    <span class="auth-chip">
                        @if ($icon === 'lock')
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        @elseif ($icon === 'treds')
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.09a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.09a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        @else
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        @endif
                        {{ $label }}
                    </span>
                @endforeach
            </div>
        </div>
        <div class="auth-foot">&copy; {{ config('app.name') }} · {{ config('paykaro.demo') ? 'demo' : 'workspace' }}</div>
    </div>
    <div class="auth-form">
        <div class="auth-wrap">
            {{ $slot }}
        </div>
    </div>
</div>
@include('partials.theme-toggle')
</body>
</html>
