<x-layouts.public title="Contact" active="contact">
<section class="sec" style="padding:5rem 0 4rem;">
	<div class="container">
		<div class="split">
			<div>
				<p class="eyebrow">Get in touch</p>
				<h1 class="display" style="font-size:clamp(2rem,4vw,3rem);margin-top:.5rem;">We'd love to hear from you</h1>
				<p class="lede" style="margin-top:1.2rem;">Whether you have a question about features, pricing, need a demo, or anything else — our team is ready to answer all your questions.</p>
				<div style="margin-top:2rem;">
					<div class="feature-card" style="margin-bottom:1rem;">
						<div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
						<h3>Email</h3>
						<p>support@paykaro.in</p>
					</div>
					<div class="feature-card" style="margin-bottom:1rem;">
						<div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg></div>
						<h3>Phone</h3>
						<p>+91 98765 43210</p>
					</div>
				</div>
			</div>
			<div>
				<div class="price-card" style="padding:2rem;">
					<h2 style="font-family:var(--n-font);font-size:1.2rem;font-weight:700;margin-bottom:1.5rem;">Send us a message</h2>
					<form method="post" action="/contact" style="display:flex;flex-direction:column;gap:1rem;">
						<div class="pkg-field" style="margin:0;">
							<label class="pkg-label">Your name</label>
							<input class="pkg-input" type="text" name="name" placeholder="Full name" required>
						</div>
						<div class="pkg-field" style="margin:0;">
							<label class="pkg-label">Work email</label>
							<input class="pkg-input" type="email" name="email" placeholder="you@company.in" required>
						</div>
						<div class="pkg-field" style="margin:0;">
							<label class="pkg-label">Message</label>
							<textarea class="pkg-input pkg-textarea" name="message" placeholder="How can we help?" required style="min-height:8rem;"></textarea>
						</div>
						<button class="pbtn pbtn-primary pbtn-block" type="submit">Send message</button>
					</form>
				</div>
			</div>
		</div>
	</div>
</section>
</x-layouts.public>
