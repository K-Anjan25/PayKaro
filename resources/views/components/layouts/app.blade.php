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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    @include('partials.datepicker')
</head>
<body class="pkg">
<div class="app-shell">
    <div class="app-util">
        <div class="app-util-inner">
            <div class="app-util-left">
                <span class="app-util-business">{{ $business?->name ?? config('app.name') }}</span>
                <span class="app-util-dot">·</span>
                <span class="app-util-chip">{{ config('paykaro.demo') ? 'Demo workspace' : 'Live workspace' }}</span>
                <a class="app-util-link" href="{{ route('pricing') }}">Pricing</a>
                <a class="app-util-link" href="{{ route('help') }}">Help</a>
            </div>
            <div class="app-util-right">
                <a class="app-util-link" href="{{ route('contact') }}">Contact</a>
                <form method="post" action="{{ route('logout') }}" style="margin:0;">
                    @csrf
                    <button class="app-util-link app-util-button" type="submit">Sign out</button>
                </form>
            </div>
        </div>
    </div>

    <header class="app-head">
        <div class="app-head-main">
            <a class="app-brand" href="{{ route('dashboard') }}" aria-label="{{ config('app.name') }} home">
                <span class="app-brand-word"><x-brand-wordmark /></span>
                <span class="app-brand-sub">Receivables &amp; Cashflow</span>
            </a>

            <form class="app-search" action="{{ route('invoices.index') }}" method="get" role="search" aria-label="Search invoices">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/></svg>
                <input type="search" name="q" placeholder="Search invoices, buyers, GSTIN..." value="{{ request('q') }}">
            </form>

            <div class="app-actions">
                @can('create', App\Models\Invoice::class)
                    <a class="pkg-btn pkg-btn--primary app-new-btn" href="{{ route('invoices.create') }}">+ New invoice</a>
                @endcan

                <div class="app-usercard">
                    @if ($user?->avatar_url)
                        <img class="app-avatar" src="{{ $user->avatar_url }}" alt="{{ $user->name }}">
                    @else
                        <span class="app-avatar app-avatar--fallback">{{ $user?->initials() ?? 'P' }}</span>
                    @endif
                    <div>
                        <div class="app-user-name">{{ $user?->name }}</div>
                        <div class="app-user-role">{{ strtoupper($user?->role->label() ?? 'user') }}</div>
                    </div>
                </div>

                <button class="app-menu" type="button" aria-label="Open menu" aria-controls="pkg-drawer" aria-expanded="false" onclick="var d=document.getElementById('pkg-drawer');var ex=this.getAttribute('aria-expanded')==='true';this.setAttribute('aria-expanded',String(!ex));d.classList.toggle('is-open',!ex);">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>
            </div>
        </div>

        <div class="app-head-nav">
            <nav class="app-nav" aria-label="Primary">
                @foreach ($nav as $key => $item)
                    <a class="{{ $active === $key ? 'is-active' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                @endforeach
            </nav>
        </div>

        <nav class="app-drawer" id="pkg-drawer" aria-label="Mobile navigation">
            @foreach ($nav as $key => $item)
                <a class="{{ $active === $key ? 'is-active' : '' }}" href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
            @endforeach
            <a href="{{ route('pricing') }}">Pricing</a>
            <a href="{{ route('help') }}">Help</a>
            <a href="{{ route('contact') }}">Contact</a>
            <form method="post" action="{{ route('logout') }}" style="margin:.6rem 0 0;">
                @csrf
                <button class="pkg-btn pkg-btn--sm pkg-btn--outline" type="submit">Sign out</button>
            </form>
        </nav>
    </header>

    <div class="app-main-wrap">
        <main class="pkg-main">
            <x-flash />
            {{ $slot }}
        </main>

        <footer class="app-footer">
            <span>© {{ now()->year }} {{ config('app.name') }}. MSMED Act 2006 Statutory Compliance &amp; Receivables Infrastructure.</span>
            <span class="app-footer-right">
                <span class="app-footer-verified">GSTN &amp; TReDS Verified</span>
                <a href="{{ route('help') }}">Compliance Support</a>
                <a href="{{ route('settings.edit') }}">Audit Logs</a>
            </span>
        </footer>
    </div>
</div>

@include('partials.theme-toggle')
</body>
</html>
