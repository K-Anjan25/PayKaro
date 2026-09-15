<x-layouts.public title="Brand" active="brand">
<div class="container" style="padding:5rem 0 1rem;">
    <div class="sec-head" style="grid-template-columns:1fr;">
        <div>
            <p class="eyebrow">Brand</p>
            <h1 class="display" style="font-size:clamp(2rem,4vw,3rem);margin-top:.5rem;">The brand book</h1>
            <p class="lede" style="max-width:60ch;">One page, because a brand book nobody reads is decoration. Everything here is read live from the product — the tokens come out of the stylesheet, the ratios are measured, and the type scale is whatever the CSS actually sets. If this page and the app disagree, the app is wrong and this page is a bug report.</p>
        </div>
    </div>
</div>

{{-- ---------------------------------------------------------------- the mark --}}
<section class="sec" style="padding:2rem 0;">
    <div class="container">
        <div class="pkg-card">
            <div class="pkg-cardhead">
                <div>
                    <h2 class="pkg-h2">The mark</h2>
                    <p class="pkg-sub">A wordmark and a tile. No graphic logo mark exists, and none may be added — the design package's manifest requires 100% removal of them.</p>
                </div>
            </div>

            <div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(min(18rem,100%),1fr));">
                <div class="feature-card">
                    <div style="font-size:2rem;font-weight:800;letter-spacing:-.045em;line-height:1;">Pay<span style="color:var(--n-blue);">Karo</span></div>
                    <p class="pkg-muted" style="margin-top:.6rem;">The wordmark. Two tones, split at the camel case — never at a line break, never lowercase, never with a space. It is set in the product's own font, so it needs no asset.</p>
                </div>

                <div class="feature-card">
                    <div style="display:flex;align-items:flex-end;gap:1rem;">
                        @foreach ([16, 32, 48, 180] as $size)
                            <div style="text-align:center;">
                                <div style="width:{{ $size }}px;height:{{ $size }}px;background:#0b132b;border-radius:{{ max(3, round($size * 0.14)) }}px;display:flex;align-items:center;justify-content:center;color:#eaf1ff;font-weight:800;font-size:{{ max(9, round($size * 0.62)) }}px;">P</div>
                                <div class="pkg-muted" style="font-size:.7rem;margin-top:.35rem;">{{ $size }}px</div>
                            </div>
                        @endforeach
                    </div>
                    <p class="pkg-muted" style="margin-top:.6rem;">The tile, at the four sizes that ship. Below 16px it stops being legible: <strong>16px is the floor</strong>, and that is why the tab icon is a single letter rather than the wordmark.</p>
                </div>
            </div>

            <div class="pkg-callout" style="margin-top:1.2rem;">
                <strong>Clear space: half the tile.</strong>
                The mark sits inside a square, and nothing — type, rule, edge — comes within half of that square's width of it. On the tile, the letter occupies the outer two-thirds and the accent rule runs beneath it, both centred; the top and bottom margins are therefore the clear space, already baked in. In the header and footer the wordmark gets the line-height of its own size as breathing room and nothing else.
            </div>
        </div>
    </div>
</section>

{{-- ------------------------------------------------------------- the palette --}}
<section class="sec sec-soft" style="padding:2rem 0;">
    <div class="container">
        <div class="pkg-card">
            <div class="pkg-cardhead">
                <div>
                    <h2 class="pkg-h2">Palette, measured</h2>
                    <p class="pkg-sub">Every pairing the product relies on, with the ratio it has to clear. <code>App\Brand\Palette</code> declares these and <code>BrandPaletteTest</code> fails when one drops below its floor — so a token change that hurts legibility fails the build instead of shipping.</p>
                </div>
                <span class="metric-pill">{{ count($audit) }} pairings measured</span>
            </div>

            <div class="pkg-tablewrap">
                <table class="pkg-table">
                    <thead>
                        <tr>
                            <th>Foreground</th>
                            <th>On</th>
                            <th>What it is for</th>
                            <th style="text-align:right;">Light</th>
                            <th style="text-align:right;">Dark</th>
                            <th>Floor</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pairs as $pair)
                            <tr>
                                <td><code>{{ $pair['fg'] }}</code></td>
                                <td><code>{{ $pair['bg'] }}</code></td>
                                <td class="pkg-muted">{{ $pair['use'] }}</td>
                                <td style="text-align:right;font-weight:700;color:{{ $pair['light']['passes'] ? 'var(--n-success)' : 'var(--n-coral)' }};">{{ number_format($pair['light']['ratio'], 2) }}</td>
                                <td style="text-align:right;font-weight:700;color:{{ $pair['dark']['passes'] ?? true ? 'var(--n-success)' : 'var(--n-coral)' }};">{{ isset($pair['dark']) ? number_format($pair['dark']['ratio'], 2) : '—' }}</td>
                                <td class="pkg-muted">{{ number_format($pair['min'], 1) }}:1</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

{{-- ---------------------------------------------------------------- tokens --}}
<section class="sec" style="padding:2rem 0;">
    <div class="container">
        <div class="pkg-card">
            <div class="pkg-cardhead">
                <div>
                    <h2 class="pkg-h2">Tokens</h2>
                    <p class="pkg-sub">Read from <code>public/assets/app.css</code> — the file that paints the product, so there is no second copy to keep in step. Any colour a view needs comes from here; a hex in a template is a bug.</p>
                </div>
                <span class="metric-pill">{{ count($tokens['light']) }} in :root · {{ count($tokens['dark']) }} restated in dark</span>
            </div>

            <div class="cards-row" style="grid-template-columns:repeat(auto-fill,minmax(min(15rem,100%),1fr));">
                @foreach ($tokens['light'] as $name => $hex)
                    <div style="display:flex;align-items:center;gap:.7rem;padding:.5rem .7rem;border:1px solid var(--n-line);border-radius:10px;background:var(--n-paper);">
                        <span style="width:1.6rem;height:1.6rem;border-radius:8px;background:{{ $hex }};border:1px solid var(--n-line);flex-shrink:0;"></span>
                        <span style="min-width:0;">
                            <code style="display:block;">{{ $name }}</code>
                            <span class="pkg-muted" style="font-size:.75rem;">
                                {{ $hex }}
                                @if (($tokens['dark'][$name] ?? null) && $tokens['dark'][$name] !== $hex)
                                    · dark {{ $tokens['dark'][$name] }}
                                @endif
                            </span>
                        </span>
                    </div>
                @endforeach
            </div>

            @if ($undarkened !== [])
                <div class="pkg-callout pkg-callout--coral" style="margin-top:1rem;">
                    Declared in <code>:root</code> but never restated in <code>html.dark</code>:
                    {{ implode(', ', $undarkened) }}. A token the dark theme forgets keeps its
                    light value on an obsidian surface.
                </div>
            @else
                <p class="pkg-muted" style="margin-top:1rem;">Every token is restated in the dark theme, which is asserted by <code>BrandPaletteTest</code>: a token the dark theme forgets is a token that keeps its light value on an obsidian surface.</p>
            @endif
        </div>
    </div>
</section>

{{-- ------------------------------------------------------------------ type --}}
<section class="sec sec-soft" style="padding:2rem 0;">
    <div class="container">
        <div class="pkg-card">
            <div class="pkg-cardhead">
                <div>
                    <h2 class="pkg-h2">Type</h2>
                    <p class="pkg-sub">One family, Plus Jakarta Sans, loaded 400–800 by all three layouts. The sizes below are read from the stylesheet; the roles are what the sizes are for.</p>
                </div>
            </div>

            <div class="pkg-tablewrap">
                <table class="pkg-table">
                    <thead>
                        <tr><th>Role</th><th>Spec</th><th>Where it is right</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td style="font-weight:700;">{{ $role['label'] }}</td>
                                <td class="pkg-muted">{{ $role['spec'] }}</td>
                                <td class="pkg-muted">{{ $role['note'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="pkg-tablewrap" style="margin-top:1.2rem;">
                <table class="pkg-table">
                    <thead>
                        <tr><th>Size</th><th>First rule that sets it</th><th style="text-align:right;">Rem</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($scale as $row)
                            <tr>
                                <td style="font-weight:700;">{{ $row['size'] }}</td>
                                <td class="pkg-muted"><code>{{ $row['selector'] }}</code> <span style="opacity:.7;">line {{ $row['line'] }}</span></td>
                                <td style="text-align:right;" class="pkg-muted">{{ rtrim(rtrim(number_format($row['rem'], 2), '0'), '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <p class="pkg-muted" style="margin-top:1rem;">Fluid sizes are recorded as written — the hero is a <code>clamp()</code> and reducing it to one number would be a lie. Monetary values are always set with tabular figures, so a column of rupees lines up on the digit.</p>
        </div>
    </div>
</section>

{{-- --------------------------------------------------- surface & elevation --}}
<section class="sec" style="padding:2rem 0;">
    <div class="container">
        <div class="pkg-card">
            <div class="pkg-cardhead">
                <div>
                    <h2 class="pkg-h2">Surface, corner and elevation</h2>
                    <p class="pkg-sub">Why the next screen looks like this one (§3.6). Read from the stylesheet: the three surfaces, the one border colour, the standard corner and the four-level elevation scale.</p>
                </div>
            </div>

            <div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(min(17rem,100%),1fr));">
                @foreach ($surfaces as $surface)
                    <div style="border:1px solid var(--n-line);border-radius:var(--n-radius);padding:1rem;background:var(--n-paper);box-shadow:{{ $surface['token'] === '--n-shadow' ? 'var(--n-shadow)' : ($surface['token'] === '--n-shadow-2' ? 'var(--n-shadow-2)' : ($surface['token'] === '--n-shadow-3' ? 'var(--n-shadow-3)' : 'none')) }};">
                        <div class="metric-pill metric-pill--soft">{{ $surface['label'] }}</div>
                        <div style="margin-top:.6rem;"><code>{{ $surface['token'] }}</code></div>
                        <div class="pkg-muted" style="font-size:.72rem;margin-top:.3rem;word-break:break-word;">{{ $surface['value'] }}</div>
                        <p style="margin:.6rem 0 0;font-size:.85rem;">{{ $surface['use'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(min(20rem,100%),1fr));margin-top:1.2rem;">
                <div class="pkg-card" style="margin:0;">
                    <div class="pkg-cardhead" style="padding:0 0 .6rem;">
                        <div>
                            <h3 class="pkg-h2" style="font-size:1rem;">The accent bars</h3>
                            <p class="pkg-sub">Two vertical rules, indigo and sky, beside the wordmark and at section heads. They are the one decorative element the system allows: no gradients, no illustrations, no ornament.</p>
                        </div>
                    </div>
                    <div class="pkg-bars" style="padding-left:0;height:3rem;">
                        <span class="bar-thick"></span>
                        <span class="bar-thin"></span>
                    </div>
                    <div class="pkg-muted" style="font-size:.78rem;margin-top:.4rem;"><code>.pkg-bars .bar-thick</code> (--n-blue, .55rem) · <code>.bar-thin</code> (--n-gold, .18rem)</div>
                </div>

                <div class="pkg-card" style="margin:0;">
                    <div class="pkg-cardhead" style="padding:0 0 .6rem;">
                        <div>
                            <h3 class="pkg-h2" style="font-size:1rem;">Three surfaces, in order</h3>
                            <p class="pkg-sub">A card sits on the canvas; a well sits inside a card. Never the reverse, and never a fourth tint.</p>
                        </div>
                    </div>
                    <div style="background:var(--n-canvas);border:1px solid var(--n-line);border-radius:var(--n-radius);padding:.9rem;">
                        <span class="pkg-muted" style="font-size:.72rem;">canvas</span>
                        <div style="background:var(--n-paper);border:1px solid var(--n-line);border-radius:var(--n-radius);padding:.9rem;margin-top:.4rem;">
                            <span class="pkg-muted" style="font-size:.72rem;">paper — a card</span>
                            <div style="background:var(--n-paper-2);border:1px solid var(--n-line);border-radius:10px;padding:.7rem;margin-top:.4rem;">
                                <span class="pkg-muted" style="font-size:.72rem;">well — an inset inside it</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

{{-- ----------------------------------------------------------------- voice --}}
<section class="sec" style="padding:2rem 0 4rem;">
    <div class="container">
        <div class="cards-row" style="grid-template-columns:repeat(auto-fit,minmax(min(20rem,100%),1fr));">
            <div class="pkg-card">
                <div class="pkg-cardhead">
                    <div>
                        <h2 class="pkg-h2">Voice</h2>
                        <p class="pkg-sub">Plain, specific, and never louder than the number it is describing.</p>
                    </div>
                </div>
                <ul style="margin:0;padding-left:1.1rem;line-height:1.85;">
                    <li><strong>Say the number.</strong> "₹1,53,400 due 18 Sep 2026", not "payment pending".</li>
                    <li><strong>Name the statute once.</strong> Section 16, MSMED Act 2006 — then stop citing it.</li>
                    <li><strong>Admit what the product does not do.</strong> The security page lists our own limits; that is the voice, not a hedge.</li>
                    <li><strong>Never blame the buyer.</strong> A reminder is a document, not a rebuke.</li>
                    <li><strong>One promise, one key.</strong> <code>{{ config('paykaro.headline') }}</code> is stated in the app and nowhere re-typed — <code>BrandCopyTest</code> asserts the rendered page follows the config.</li>
                </ul>
            </div>

            <div class="pkg-card">
                <div class="pkg-cardhead">
                    <div>
                        <h2 class="pkg-h2">Never</h2>
                        <p class="pkg-sub">The short list, because a style guide that only says "be consistent" is not one.</p>
                    </div>
                </div>
                <ul style="margin:0;padding-left:1.1rem;line-height:1.85;">
                    <li>A graphic logo mark, a mascot, or an illustration of a person.</li>
                    <li>A hex value in a Blade template — the token exists.</li>
                    <li>White text on an accent in the dark theme: the accents are light there, so the text is obsidian (<code>--n-on-accent</code>).</li>
                    <li>A second promise. The descriptor is not a headline.</li>
                    <li>Interest, due dates or balances typed by hand — they are computed, and a typed one goes to a claim.</li>
                    <li>Legalese in marketing copy, or marketing copy in a legal one.</li>
                </ul>
            </div>
        </div>

        <div class="pkg-card" style="margin-top:1.2rem;">
            <div class="pkg-cardhead">
                <div>
                    <h2 class="pkg-h2">Regenerating the assets</h2>
                    <p class="pkg-sub">Icons and the share card are committed files — the app has no build step — but they are generated, so they can be re-rendered when the palette or the wordmark moves.</p>
                </div>
            </div>
            <pre style="overflow-x:auto;margin:0;padding:1rem;background:var(--n-paper-2);border:1px solid var(--n-line);border-radius:10px;font-size:.82rem;line-height:1.7;"><code>cd /tmp &amp;&amp; npm pack @expo-google-fonts/plus-jakarta-sans &amp;&amp; tar xzf *.tgz
cd -   &amp;&amp; FONT_DIR=/tmp/package node bridge/brand-assets.mjs</code></pre>
            <p class="pkg-muted" style="margin-top:.8rem;">
                Outputs <code>public/favicon.ico</code> (16/32/48),
                <code>public/assets/img/icon-32|180|512.png</code> and
                <code>public/assets/img/og-default.png</code> (1200×630). The generator refuses to
                write a share card whose copy overruns its text column, and
                <code>BrandAssetsTest</code> pins the sizes and the tags that point at them.
            </p>
        </div>
    </div>
</section>
</x-layouts.public>
