<x-layouts.public title="Help" active="help">
<section class="sec" style="padding:5rem 0 4rem;">
	<div class="container">
		<div class="sec-head" style="grid-template-columns:1fr;">
			<div>
				<p class="eyebrow">Support</p>
				<h1 class="display" style="font-size:clamp(2rem,4vw,3rem);margin-top:.5rem;">How can we help you?</h1>
			</div>
		</div>
		<div class="cards-row" style="grid-template-columns:repeat(3,1fr);margin-top:2rem;">
			<article class="feature-card">
				<div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H7a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7z"/><path d="M14 2v5h5"/><path d="M12 11v6M9 14h6"/></svg></div>
				<h3>Getting started</h3>
				<p>Learn how to set up your account, add your first buyer, and raise your first invoice in minutes.</p>
			</article>
			<article class="feature-card">
				<div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 6v6c0 5 3.5 9.5 8 10 4.5-.5 8-5 8-10V6l-8-4z"/><path d="m9 12 2 2 4-4"/></svg></div>
				<h3>TReDS &amp; Financing</h3>
				<p>Understand how the finance queue works and how to get your invoices ready for TReDS financing.</p>
			</article>
			<article class="feature-card">
				<div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
				<h3>Claims &amp; Disputes</h3>
				<p>File MSEFC claims, start mediation, or build an evidence packet for arbitration.</p>
			</article>
		</div>
		<div class="cta-banner" style="margin-top:3rem;">
			<div>
				<h2 style="font-family:var(--n-display);font-size:1.5rem;">Still need help?</h2>
				<p>Contact our support team and we'll get back to you within 24 hours.</p>
			</div>
			<a class="pbtn pbtn-primary pbtn-lg" href="{{ route('contact') }}">Contact support</a>
		</div>
	</div>
</section>
</x-layouts.public>
