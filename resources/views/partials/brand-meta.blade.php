@php
    /*
     * The shared <head> furniture: the icon set and the social/share identity.
     *
     * BRAND_PLAN §3.1 and §4.3 record both as missing — `public/favicon.ico` was a
     * 0-byte file, and there was no `og:` tag anywhere, so a PayKaro link pasted
     * into WhatsApp (the channel this market actually shares links on) rendered
     * with no title, no description and no image.
     *
     * One partial, included by all three layouts, so the four tags that have to
     * agree with each other cannot drift apart. Callers pass the page's own copy;
     * the defaults are the product's.
     *
     * `og:image` must be *absolute* — a crawler has no page to resolve a relative
     * one against — so it is built with asset(), which reads APP_URL. The sandbox
     * bridge overrides APP_URL per request, which is what keeps the preview host's
     * share card working there too.
     *
     * @var string|null $metaTitle
     * @var string|null $metaDescription
     * @var string|null $metaType
     */
    $metaTitle = $metaTitle ?? config('app.name').' — Make every invoice count';
    $metaDescription = $metaDescription ?? 'Track every invoice from raised to settled, with the evidence checklist, statutory interest under the MSMED Act and the liquidity it unlocks.';
    $metaType = $metaType ?? 'website';
@endphp

<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/img/icon-32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('assets/img/icon-180.png') }}">

<meta name="description" content="{{ $metaDescription }}">
<meta name="theme-color" content="#0b132b">

<meta property="og:type" content="{{ $metaType }}">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $metaTitle }}">
<meta property="og:description" content="{{ $metaDescription }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('assets/img/og-default.png') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ config('app.name') }} — {{ config('paykaro.tagline') }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $metaTitle }}">
<meta name="twitter:description" content="{{ $metaDescription }}">
<meta name="twitter:image" content="{{ asset('assets/img/og-default.png') }}">
