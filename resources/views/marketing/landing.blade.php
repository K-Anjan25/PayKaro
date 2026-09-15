<x-layouts.public mode="landing" mainId="top" :search="true">
<!-- HERO -->
<section class="hero hero-grid">
	<div class="container">
		<div class="hero-inner">
			<div class="hero-text">
				<div class="hero-eyebrow"><span class="dot"></span> MSME receivables, simplified</div>
				@php
					// The hero is the headline's typographic treatment: the last two words
					// take the italic and the accent. Split from config, never retyped —
					// a renamed deployment or a new headline must not need a markup edit.
					$headline = preg_split('/\s+/', (string) config('paykaro.headline'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
					$emphasis = array_splice($headline, -2);
				@endphp
				<h1 class="hero-title display">@if (count($emphasis) === 2){{ implode(' ', $headline) }}<br><em>{{ $emphasis[0] }}</em> <span class="accent">{{ $emphasis[1] }}</span>@else{{ config('paykaro.headline') }}@endif</h1>
				<p class="hero-lede">PayKaro turns an invoice into a finance-ready asset — one pipeline for what's owed, what's overdue, and what you could finance today, with the evidence and interest numbers that make a claim actually stand.</p>
				<div class="hero-cta">
					<a class="pbtn pbtn-primary pbtn-lg" href="{{ route('register') }}">Try PayKaro free</a>
					<a class="pbtn pbtn-outline pbtn-lg" href="#workflow">See how it works</a>
				</div>
				<div class="hero-trust">
					<span class="t"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.5 9.5 8 10 4.5-.5 8-5 8-10V6l-8-4z"/><path d="m9 12 2 2 4-4"/></svg></span> Statutory interest computed</span>
					<span class="t"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="m9 14 2 2 4-4"/></svg></span> TReDS-ready finance queue</span>
					<span class="t"><span class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span> MSEFC claim packets</span>
				</div>
			</div>
			<div class="hero-art">
				<div class="hero-bars" aria-hidden="true">
					<span class="b-thick"></span>
					<span class="b-thin"></span>
				</div>
				<img class="hero-image" src="/assets/img/hero-owner.jpg" alt="MSME factory owner, India">
				<div class="hero-card hero-card-1">
					<p class="pip">Live queue</p>
					<p class="hero-card-label" style="margin-top:.4rem;">Outstanding today</p>
					<p class="hero-card-value num">₹42.8L</p>
					<p class="hero-card-note">15 invoices · 3 buyers</p>
				</div>
				<div class="hero-card hero-card-2">
					<p class="hero-card-label">Ready to finance</p>
					<p class="hero-card-value num">₹18.2L</p>
					<p class="hero-card-note">buyers on TReDS</p>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- STAT BAR -->
<section class="statbar">
	<div class="container">
		<div class="statbar-inner">
			<div class="statbar-item"><p class="v">₹4.2Cr+</p><p class="l">Receivables tracked</p></div>
			<div class="statbar-item"><p class="v">45 days</p><p class="l">MSME due window</p></div>
			<div class="statbar-item"><p class="v">3×</p><p class="l">Bank-rate interest</p></div>
			<div class="statbar-item"><p class="v">60s</p><p class="l">To onboard</p></div>
		</div>
	</div>
</section>

<!-- WORKFLOW (4 step pipeline) -->
<section id="workflow" class="sec">
	<div class="container">
		<div class="sec-head">
			<div>
				<p class="eyebrow">One pipeline</p>
				<h2 class="display">Raised → accepted → financed → settled</h2>
			</div>
			<p class="lede">No more scattered spreadsheets. Every invoice moves through the same pipeline with a live balance and accruing statutory interest — so you always know where the money is.</p>
		</div>
		<div class="cards-row">
			<div class="step-card"><span class="num">01</span><h3>Raised</h3><p>Log the invoice from day one — GST-valid copy, dates and terms all in one place.</p></div>
			<div class="step-card step-card--gold"><span class="num">02</span><h3>Accepted</h3><p>Capture the buyer's acceptance and the delivery acknowledgement that proves it.</p></div>
			<div class="step-card step-card--soft"><span class="num">03</span><h3>Financed</h3><p>Discounted on TReDS the moment the evidence is complete and the buyer is onboard.</p></div>
			<div class="step-card step-card--coral"><span class="num">04</span><h3>Settled</h3><p>Reconciled, closed and out of your queue — with a clean paper trail behind it.</p></div>
		</div>
	</div>
</section>

<!-- FINANCE (2-up) -->
<section id="finance" class="sec sec-soft">
	<div class="container">
		<div class="split">
			<div>
				<p class="eyebrow">Financing</p>
				<h2 class="display">Unlock cash that's already yours.</h2>
				<p class="lede">Our finance queue separates the invoices you can finance today from the evidence gaps holding the rest back — buy it ready, not hoping.</p>
				<a class="pbtn pbtn-blue" href="/treds" style="margin-top:1.4rem;">Open the finance queue →</a>
			</div>
			<div class="queue-card">
				<div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;">
					<div>
						<p class="label">TReDS queue</p>
						<h3>Financeable today</h3>
					</div>
					<span class="pbtn pbtn-ghost pbtn-sm" style="pointer-events:none;">4 invoices</span>
				</div>
				<div class="queue-row">
					<span class="dot gold"></span>
					<div><p class="name">Ready</p><p class="note">Evidence complete · buyer on TReDS</p></div>
					<p class="amt">₹18.2L</p>
				</div>
				<div class="queue-row">
					<span class="dot coral"></span>
					<div><p class="name">Gap — buyer not onboard</p><p class="note">Confirm TReDS to unlock ₹24.6L</p></div>
					<p class="amt">₹24.6L</p>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- EVIDENCE (3-up) -->
<section id="evidence" class="sec">
	<div class="container">
		<div class="sec-head">
			<div>
				<p class="eyebrow">Evidence</p>
				<h2 class="display">Stand on the right numbers.</h2>
			</div>
		</div>
		<div class="cards-row" style="grid-template-columns:repeat(auto-fill,minmax(min(16.5rem,100%),1fr));">
			<article class="feature-card feature-card--gold">
				<div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/></svg></div>
				<h3>Evidence that stands up</h3>
				<p>PO, delivery ack, GRN and a GST-valid copy — a finance or dispute packet that's never missing a document.</p>
			</article>
			<article class="feature-card">
				<div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12M6 8h12M6 13l8 4M6 18l8-4"/></svg></div>
				<h3>Interest that's correct</h3>
				<p>Statutory interest computed from the invoice at 3× the bank rate, applied daily — so a claim always uses the right number.</p>
			</article>
			<article class="feature-card feature-card--coral">
				<div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v18M3 12h18M5.6 5.6l12.8 12.8M5.6 18.4 18.4 5.6"/></svg></div>
				<h3>Claims that go through</h3>
				<p>A guided evidence packet for MSEFC, mediation or arbitration — ready to file the moment a buyer stops paying.</p>
			</article>
		</div>
	</div>
</section>

<!-- NEWS (image card grid, UFL-style) -->
<section id="news" class="sec sec-soft">
	<div class="container">
		<div class="sec-head">
			<div>
				<p class="eyebrow">News &amp; updates</p>
				<h2 class="display">From the PayKaro floor.</h2>
			</div>
			<p class="lede">What we're shipping, what we're seeing in the field, and the small changes that keep Indian MSMEs in the money.</p>
		</div>
		<div class="news-grid">
			@foreach($news as $a)
				<article class="news-card">
					<div class="img"><img src="{{ $a->image }}" alt="{{ $a->alt }}"></div>
					<div class="body">
						<p class="meta"><span class="tag">{{ $a->tag }}</span> {{ $a->date }}</p>
						<h3>{{ $a->title }}</h3>
						<p>{{ $a->excerpt }}</p>
						<a class="more" href="{{ $a->url() }}">Read more</a>
					</div>
				</article>
			@endforeach
		</div>
	</div>
</section>

<!-- IMPACT (dark) -->
<section id="impact" class="sec sec-dark">
	<div class="container">
		<div class="impact">
			<div>
				<p class="eyebrow eyebrow--on-dark">Momentum that moves MSMEs</p>
				<h2 class="display" style="margin-top:.6rem;">One platform for every invoice a small business raises.</h2>
				<p class="lede" style="margin-top:1.2rem;">From the corner-shop supplier to the precision-components shop, we're building the workflow that turns receivables into a finance-ready asset — with evidence, interest and a clear path to TReDS.</p>
				<div class="dontmiss">
					<a href="{{ route('register') }}">Try the free Starter</a>
					<a href="{{ route('pricing') }}">See pricing</a>
					<a href="{{ route('login') }}">Sign in to your workspace</a>
				</div>
			</div>
			<div>
				<div class="impact-art" style="margin-bottom:1.4rem;">
					<img src="/assets/img/impact-growth.jpg" alt="MSME business growth charts on a laptop">
					<div class="glaze"></div>
				</div>
				<div class="impact-stats">
					<div class="impact-stat"><p class="v num">₹4.2Cr+</p><div><p class="l">Receivables tracked</p><p class="n">Across active tenants in the last 30 days.</p></div></div>
					<div class="impact-stat"><p class="v num">3×</p><div><p class="l">Bank-rate interest</p><p class="n">Statutory multiplier on overdue invoices — applied daily.</p></div></div>
					<div class="impact-stat"><p class="v num">45d</p><div><p class="l">MSME due window</p><p class="n">The 2026 MSMED Act timeline, surfaced as a live deadline per invoice.</p></div></div>
				</div>
			</div>
		</div>
	</div>
</section>

<!-- CLAIMS (split) -->
<section id="claims" class="sec">
	<div class="container">
		<div class="split split--reversed">
			<div class="split-art">
				<img src="/assets/img/hero-owner.jpg" alt="MSME owner in his workshop">
				<div class="art-cap">Shree Precision Components · 15 invoices tracked</div>
			</div>
			<div>
				<p class="eyebrow">Claims</p>
				<h2 class="display">When a buyer doesn't pay, you're already ready.</h2>
				<p class="lede">The 2026 MSME rules tighten TReDS mandates and strengthen the delayed-payment forum. Businesses that keep clean, complete, dated records now have real leverage — statutory interest, a time-bound dispute forum and discounted financing.</p>
				<div style="margin-top:1.4rem;display:flex;align-items:center;gap:.7rem;color:var(--n-blue);font-weight:700;">
					<span style="display:inline-flex;width:2.2rem;height:2.2rem;border-radius:999px;background:var(--n-blue);color:var(--n-gold);align-items:center;justify-content:center;">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
					</span>
					Guided evidence packet for MSEFC, mediation &amp; arbitration
				</div>
			</div>
		</div>
	</div>
</section>

<!-- TESTIMONIALS -->
<section class="sec sec-soft">
	<div class="container">
		<div style="text-align:center;margin-bottom:2rem;">
			<p class="eyebrow">Loved by MSMEs</p>
		</div>
		<div class="testimonials">
			<figure class="testimonial">
				<blockquote>"We stopped guessing. PayKaro told us exactly which invoices were financeable and gave us the evidence to back them."</blockquote>
				<figcaption>
					<div class="av">S</div>
					<div><p class="name">Sunita Rao</p><p class="role">Shree Precision Components</p></div>
				</figcaption>
			</figure>
			<figure class="testimonial">
				<blockquote>"The finance queue paid for itself in a month. It separates what's ready from what isn't, which is the whole game."</blockquote>
				<figcaption>
					<div class="av" style="background:var(--n-coral);color:#fff;">F</div>
					<div><p class="name">Farhan Ali</p><p class="role">MetRow Ceramics</p></div>
				</figcaption>
			</figure>
		</div>
	</div>
</section>

<!-- CLOSING CTA -->
<section class="sec">
	<div class="container">
		<div class="cta-banner">
			<div>
				<p class="eyebrow" style="color:var(--n-ink);">Ready</p>
				{{-- The closing CTA repeats the brand line on purpose — it is the same
				     promise, so it is the same key, not a retyped copy of it. --}}
				<h2 style="margin-top:.5rem;">{{ config('paykaro.headline') }}</h2>
				<p>Free Starter tier. No credit card. Sign in, raise an invoice, and watch the pipeline do the work.</p>
			</div>
			<a class="pbtn pbtn-blue pbtn-lg" href="{{ route('register') }}">Try PayKaro free →</a>
		</div>
	</div>
</section>
    <x-slot:footer>
        <footer class="page-footer">
<div class="container">
	<div>
		<a class="pkg-brand" href="{{ route('landing') }}" style="padding:0;color:#fff;" aria-label="PayKaro home">
			<div class="pkg-brand-text">
				<div class="name" style="color:#fff;"><x-brand-wordmark /></div>
				<div class="sub" style="color:rgba(255,255,255,.6);">{{ config('paykaro.descriptor') }}</div>
			</div>
		</a>
		<p class="meta">© {{ now()->year }} PayKaro · Made for India's MSMEs.</p>
	</div>
	<div>
		<h4>Product</h4>
		<ul>
			<li><a href="#workflow">Workflow</a></li>
			<li><a href="#finance">Financing</a></li>
			<li><a href="#evidence">Evidence</a></li>
			<li><a href="#claims">Claims</a></li>
		</ul>
	</div>
	<div>
		<h4>Company</h4>
		<ul>
			<li><a href="#news">News</a></li>
			<li><a href="#impact">Impact</a></li>
			<li><a href="{{ route('pricing') }}">Pricing</a></li>
			<li><a href="{{ route('login') }}">Sign in</a></li>
		</ul>
	</div>
	<div>
		<h4>Legal</h4>
		<ul>
			<li><a href="{{ route('terms') }}">Terms</a></li>
			<li><a href="{{ route('privacy') }}">Privacy</a></li>
			<li><a href="{{ route('security') }}">Security</a></li>
		</ul>
	</div>
</div>
<div class="page-footer-bottom">
	<span>© {{ now()->year }} {{ config('app.name') }} · {{ config('paykaro.descriptor') }}</span>
	<span>
		<a href="{{ route('brand') }}" style="color:var(--n-gold);">Brand</a> ·
		Demo workspace · <a href="{{ route('login') }}" style="color:var(--n-gold);">Sign in</a>
	</span>
</div>
</footer>
    </x-slot:footer>
</x-layouts.public>
