<x-layouts.public title="Pricing" active="pricing">
<section class="sec" style="padding:5rem 0 4rem;">
	<div class="container">
		<div class="sec-head">
			<div>
				<p class="eyebrow">Pricing</p>
				<h1 class="display" style="font-size:clamp(2.4rem,4.8vw,3.8rem);">Simple plans that pay for themselves.</h1>
			</div>
			<p class="lede">Every plan tracks receivables, computes interest and builds an evidence-ready claim. Upgrade when you want to finance and export more.</p>
		</div>
		<div class="price-grid">
			@foreach($plans as $p)
				<div class="price-card {{ $p['highlight'] ? 'is-hot' : '' }}">
					<div style="display:flex;align-items:center;justify-content:space-between;">
						<span class="label">{{ $p['name'] }}</span>
						@if($p['highlight'])<span class="pbtn pbtn-ghost pbtn-sm" style="pointer-events:none;background:var(--n-gold);color:#0f1d2e;border-color:var(--n-gold);">Most popular</span>@endif
					</div>
					<div class="price">{{ $p['price'] }}</div>
					<div class="per">{{ $p['per'] }}</div>
					<p class="blurb">{{ $p['blurb'] }}</p>
					<ul>
						@foreach($p['features'] as $f)
							<li><span class="ck">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-11"/></svg>
							</span>{{ $f }}</li>
						@endforeach
					</ul>
					<a class="pbtn {{ $p['cta_class'] }} pbtn-block cta" href="{{ $p['cta'] }}">{{ $p['cta_label'] }}</a>
				</div>
			@endforeach
		</div>
		<div style="margin-top:3rem;text-align:center;">
			<p class="display" style="font-size:1.6rem;">Not sure which plan?</p>
			<p class="lede" style="margin-top:.6rem;">Every plan starts on the free Starter tier — <a href="{{ route('login') }}" style="color:var(--n-blue);font-weight:700;text-decoration:underline;text-underline-offset:4px;">sign in</a> and track your first invoices today.</p>
		</div>
	</div>
</section>
</x-layouts.public>
