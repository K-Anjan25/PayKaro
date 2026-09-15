<x-layouts.public :title="$article->title">
<section class="sec" style="padding:3.2rem 0 3.5rem;">
	<div class="container">
		<a class="pbtn pbtn-outline pbtn-sm" href="{{ route('landing') }}#news">← All product notes</a>
		<div class="article-wrap">
			{{-- Authored by the team, and labelled as such rather than dressed as
			     reporting (BRAND_PLAN §4). --}}
			<p class="article-meta"><span class="tag">{{ $article->tag }}</span> {{ $article->date }} · Written by the PayKaro team</p>
			<h1 class="article-title">{{ $article->title }}</h1>
			<p class="article-dek">{{ $article->excerpt }}</p>
		</div>
		<div class="article-hero">
			<img src="{{ $article->image }}" alt="{{ $article->alt }}">
		</div>
		<div class="article-body">
			@foreach($article->blocks() as $block)
				@if($block[0] === 'heading')
					<h2>{{ $block[1] }}</h2>
				@else
					<p>{{ $block[1] }}</p>
				@endif
			@endforeach
		</div>
		<div class="article-cta">
			<p class="eyebrow eyebrow--on-dark">See it with your own invoices</p>
			<h2 class="display">{{ config('paykaro.headline') }}</h2>
			<p>Track what's owed, what's overdue and what you could finance today — with the evidence and interest numbers that make a claim stand.</p>
			<div style="margin-top:1.3rem;display:flex;gap:.7rem;justify-content:center;flex-wrap:wrap;">
				<a class="pbtn pbtn-primary" href="{{ route('register') }}">Start free</a>
				<a class="pbtn pbtn-ghost" href="{{ route('login') }}">Sign in to the demo</a>
			</div>
		</div>
	</div>
</section>
    <x-slot:footer>
        <footer class="page-footer">
            <div class="page-footer-bottom">
                <span>© {{ now()->year }} {{ config('app.name') }} · Made for India's MSMEs</span>
                <span>
                    <a href="{{ route('landing') }}#news" style="color:var(--n-gold);">More news</a> ·
                    <a href="{{ route('brand') }}" style="color:var(--n-gold);">Brand</a> ·
                    <a href="{{ route('landing') }}" style="color:var(--n-gold);">Back to home</a>
                </span>
            </div>
        </footer>
    </x-slot:footer>
</x-layouts.public>
