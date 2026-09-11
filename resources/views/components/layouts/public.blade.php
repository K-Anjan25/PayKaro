@props([
    'title' => null,
    'active' => null,
    // 'landing' anchors its nav to sections on the same page and offers Claims;
    // every other public page anchors back into the landing page and offers
    // Pricing. Same header, one prop apart.
    'mode' => 'page',
    'mainId' => null,
])

@php
    $landing = $mode === 'landing';
    $anchors = $landing ? '' : '/';

    $nav = collect([
        ['id' => 'workflow', 'label' => 'Workflow', 'href' => '/#workflow'],
        ['id' => 'finance', 'label' => 'Financing', 'href' => '/#finance'],
        ['id' => 'news', 'label' => 'News', 'href' => '/#news'],
        ['id' => 'impact', 'label' => 'Impact', 'href' => '/#impact'],
    ])->map(fn (array $item) => [
        ...$item,
        'href' => $landing ? substr($item['href'], 1) : $item['href'],
    ]);

    $nav = $nav->concat([
        $landing
            ? ['id' => 'claims', 'label' => 'Claims', 'href' => '#claims']
            : ['id' => 'pricing', 'label' => 'Pricing', 'href' => route('pricing')],
    ]);
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name').' — Make every invoice count' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="page">

<div class="pkg-util">
    <div class="pkg-util-inner">
        <span class="pkg-util-tag"><span class="dot"></span> MSME receivables</span>
        @if ($landing)
            <span class="pkg-util-sep"></span>
            <a class="pkg-util-hide" href="#workflow">Workflow</a>
            <span class="pkg-util-sep"></span>
            <a class="pkg-util-hide" href="#finance">Financing</a>
            <span class="pkg-util-sep"></span>
            <a class="pkg-util-hide" href="{{ route('pricing') }}">Pricing</a>
        @endif
        <div class="pkg-util-right">
            @if ($landing)
                <a href="{{ route('contact') }}">Contact</a>
                <span class="pkg-util-sep"></span>
            @endif
            @auth
                <a href="{{ route('dashboard') }}">Open workspace</a>
            @else
                <a href="{{ route('login') }}">Sign in</a>
            @endauth
        </div>
    </div>
</div>

<header class="pkg-head">
    <div class="pkg-head-inner">
        <div class="pkg-bars" aria-hidden="true">
            <span class="bar-thick"></span>
            <span class="bar-thin"></span>
        </div>
        <a class="pkg-brand" href="{{ route('landing') }}" aria-label="{{ config('app.name') }} home">
            <x-logo-mark />
            <div class="pkg-brand-text">
                <div class="name"><x-brand-wordmark /></div>
                <div class="sub">{{ config('paykaro.tagline') }}</div>
            </div>
        </a>
        <nav class="pkg-nav" aria-label="Primary">
            @foreach ($nav as $item)
                <a class="{{ $active === $item['id'] ? 'is-active' : '' }}" href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @endforeach
        </nav>
        <div class="pkg-head-right">
            @isset($search)
                <label class="pkg-searchbox" aria-label="Search">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                    <input type="search" placeholder="Search…">
                </label>
            @endisset
            @auth
                <a class="pbtn pbtn-outline pbtn-sm" href="{{ route('dashboard') }}">Dashboard</a>
                <a class="pbtn pbtn-primary pbtn-sm" href="{{ route('invoices.create') }}">+ New invoice</a>
            @else
                <a class="pbtn pbtn-outline pbtn-sm" href="{{ route('login') }}">Sign in</a>
                <a class="pbtn pbtn-primary pbtn-sm" href="{{ route('register') }}">Get started</a>
            @endauth
        </div>
    </div>
    <div class="pkg-headline" aria-hidden="true"></div>
</header>

<main @if ($mainId) id="{{ $mainId }}" @endif>
    {{ $slot }}
</main>

@isset($footer)
    {{ $footer }}
@else
    <footer class="page-footer">
        <div class="page-footer-bottom">
            <span>© {{ now()->year }} {{ config('app.name') }} · Made for India's MSMEs</span>
            <span><a href="{{ route('landing') }}" style="color:var(--n-gold);">Back to home</a></span>
        </div>
    </footer>
@endisset

@include('partials.theme-toggle')
</body>
</html>
