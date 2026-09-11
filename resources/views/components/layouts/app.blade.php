@props(['title' => null, 'active' => 'dashboard'])

@php
    $user = auth()->user();
    $business = $user?->business;

    $nav = [
        'dashboard' => ['route' => 'dashboard', 'label' => 'Overview'],
        'invoices' => ['route' => 'invoices.index', 'label' => 'Invoices'],
        'buyers' => ['route' => 'buyers.index', 'label' => 'Customers'],
        'treds' => ['route' => 'treds', 'label' => 'Finance'],
        'reports' => ['route' => 'reports', 'label' => 'Reports'],
        'settings' => ['route' => 'settings.edit', 'label' => 'Settings'],
    ];
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title.' — ' : '' }}{{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
</head>
<body class="pkg">
<div class="pkg-shell">

    {{-- Utility bar --}}
    <div class="pkg-util">
        <div class="pkg-util-inner">
            <span class="pkg-util-tag">
                <span class="dot"></span>
                {{ config('paykaro.demo') ? 'Demo workspace' : $business?->name ?? config('app.name') }}
            </span>
            <span class="pkg-util-sep"></span>
            <a class="pkg-util-hide" href="{{ route('pricing') }}">Pricing</a>
            <span class="pkg-util-sep"></span>
            <a class="pkg-util-hide" href="{{ route('help') }}">Help</a>
            <div class="pkg-util-right">
                <a href="{{ route('contact') }}">Contact</a>
                <span class="pkg-util-sep"></span>
                <span class="pkg-util-hide">Welcome, {{ $business?->name ?? config('app.name') }}</span>
            </div>
        </div>
    </div>

    {{-- Main header --}}
    <header class="pkg-head">
        <div class="pkg-head-inner">
            <div class="pkg-bars" aria-hidden="true">
                <span class="bar-thick"></span>
                <span class="bar-thin"></span>
            </div>
            <a class="pkg-brand" href="{{ route('dashboard') }}" aria-label="{{ config('app.name') }} home">
                <x-logo-mark />
                <div class="pkg-brand-text">
                    <div class="name"><x-brand-wordmark /></div>
                    <div class="sub">MSME receivables · {{ $business?->name }}</div>
                </div>
            </a>
            <nav class="pkg-nav" aria-label="Primary">
                @foreach ($nav as $key => $item)
                    <a class="{{ $active === $key ? 'is-active' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="pkg-head-right">
                <form class="pkg-searchbox" action="{{ route('invoices.index') }}" method="get" role="search" aria-label="Search invoices">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                    <input type="search" name="q" placeholder="Search invoices, buyers…" value="{{ request('q') }}">
                </form>
                <a class="pkg-btn pkg-btn--sm pkg-btn--outline" href="{{ route('pricing') }}">Pricing</a>
                <form method="post" action="{{ route('logout') }}" style="margin:0;">
                    @csrf
                    <button class="pkg-btn pkg-btn--sm pkg-btn--gold" type="submit" title="Log out">Log out</button>
                </form>
                <button class="pkg-menumob" type="button" aria-label="Open menu" aria-controls="pkg-drawer" aria-expanded="false" onclick="var d=document.getElementById('pkg-drawer');var ex=this.getAttribute('aria-expanded')==='true';this.setAttribute('aria-expanded',String(!ex));d.classList.toggle('is-open',!ex);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>
            </div>
        </div>
        <div class="pkg-headline" aria-hidden="true"></div>
        <nav class="pkg-drawer" id="pkg-drawer" aria-label="Mobile navigation">
            @foreach ($nav as $key => $item)
                <a class="{{ $active === $key ? 'is-active' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
            @endforeach
            <a href="{{ route('pricing') }}">Pricing</a>
        </nav>
    </header>

    {{-- Main content --}}
    <div class="pkg-main-wrap">
        <main class="pkg-main">
            <x-flash />
            {{ $slot }}
        </main>
        <footer class="pkg-footer">
            © {{ now()->year }} {{ config('app.name') }} · {{ config('paykaro.tagline') }} ·
            <a href="{{ route('pricing') }}" style="color:var(--n-blue);font-weight:700;">See pricing</a>
        </footer>
    </div>
</div>

@include('partials.theme-toggle')
</body>
</html>
