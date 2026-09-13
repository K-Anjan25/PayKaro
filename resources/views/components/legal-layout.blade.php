@props([
    'title' => null,
    'kicker' => 'Legal',
    'active' => 'legal',
    'lede' => null,
    'sections' => [],
])

@php
    $slugify = fn (string $text) => \Illuminate\Support\Str::slug($text);
@endphp

{{--
    One component for Terms, Privacy and Security, so the three read as a single
    document set rather than three pages written on three different days: same
    header, same anchored contents rail, same prose measure, same callout style.

    Every string passes through {{ }} on purpose — legal copy that renders raw is
    a self-inflicted XSS in the one place users read most closely.
--}}
<x-layouts.public :title="$title" :active="$active">
<section class="sec legal-sec">
    <div class="container">
        <div class="legal-head">
            <div>
                <p class="eyebrow">{{ $kicker }}</p>
                <h1 class="display legal-h1">{{ $title }}</h1>
                @if ($lede)
                    <p class="lede legal-lede">{{ $lede }}</p>
                @endif
            </div>
            <p class="legal-stamp">
                Applies to this codebase as checked out.<br>
                Last revised {{ now()->timezone(config('app.timezone'))->format('d M Y') }}.
            </p>
        </div>

        <div class="legal-body">
            <nav class="legal-toc" aria-label="Sections">
                <p class="legal-toc-h">On this page</p>
                <ol>
                    @foreach ($sections as $section)
                        <li><a href="#{{ $slugify($section['heading']) }}">{{ $section['heading'] }}</a></li>
                    @endforeach
                </ol>
                <div class="legal-toc-links">
                    @foreach ([['Terms', 'terms'], ['Privacy', 'privacy'], ['Security', 'security']] as [$label, $route])
                        @unless ($title === $label)
                            <a href="{{ route($route) }}">{{ $label }}</a>
                        @endunless
                    @endforeach
                </div>
            </nav>

            <div class="legal-prose">
                @foreach ($sections as $section)
                    <section class="legal-part" id="{{ $slugify($section['heading']) }}">
                        <h2>{{ $section['heading'] }}</h2>
                        @foreach (preg_split('/\n\s*\n/', trim($section['body'])) as $para)
                            <p>{{ $para }}</p>
                        @endforeach
                        @if (! empty($section['list']))
                            <ul>
                                @foreach ($section['list'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @if (! empty($section['note']))
                            <p class="legal-note"><strong>{{ $section['note'][0] }}</strong> {{ $section['note'][1] }}</p>
                        @endif
                    </section>
                @endforeach
            </div>
        </div>
    </div>
</section>
</x-layouts.public>
