<x-layouts.auth title="Create account" copy="Register your enterprise and owner profile in one step to enforce statutory prompt-payment workflows from the first invoice.">
    <div class="screen-kicker">Unified MSME registration</div>
    <h1 class="auth-h">Create your account</h1>
    <p class="auth-sub">Register your enterprise and owner profile in one step.</p>

    @if (session('status'))
        <div class="auth-err">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-err">{{ $errors->first() }}</div>
    @endif

    <x-auth.google-button label="Sign up with Google" />
    @if (\App\Support\GoogleSignIn::enabled())
        <div class="auth-or">or register with enterprise email</div>
    @endif

    <form method="post" action="{{ route('register') }}">
        @csrf
        <label class="auth-lbl" for="business_name">Business name</label>
        <input class="auth-in" id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" placeholder="e.g. Shree Precision Components">
        @error('business_name')<div class="auth-err">{{ $message }}</div>@enderror

        <label class="auth-lbl" for="name">Your name</label>
        <input class="auth-in" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Sunita Rao" required autofocus>
        @error('name')<div class="auth-err">{{ $message }}</div>@enderror

        <label class="auth-lbl" for="email">Work email</label>
        <input class="auth-in" id="email" type="email" name="email" value="{{ old('email') }}" placeholder="e.g. you@company.in" required autocomplete="email">
        @error('email')<div class="auth-err">{{ $message }}</div>@enderror

        <label class="auth-lbl" for="password">Password</label>
        <input class="auth-in" id="password" type="password" name="password" placeholder="Create a secure password" required minlength="8" autocomplete="new-password">
        @error('password')<div class="auth-err">{{ $message }}</div>@enderror

        <label style="display:flex;gap:.45rem;align-items:flex-start;margin:.2rem 0 1rem;font-size:.84rem;color:var(--n-ink-mute);">
            <input type="checkbox" name="terms" value="1" style="margin-top:.2rem;">
            <span>I agree to the <a href="{{ route('terms') }}" style="color:var(--n-blue);font-weight:700;">Terms of Service</a>, <a href="{{ route('privacy') }}" style="color:var(--n-blue);font-weight:700;">Privacy Policy</a> and statutory MSMED audit-compliance protocol.</span>
        </label>

        <button class="auth-cta" type="submit">Create account →</button>
    </form>

    <div class="auth-alt">Already registered? <a href="{{ route('login') }}">Sign in</a></div>

    <div class="auth-mini-grid" style="margin-top:1.25rem;">
        <div class="auth-mini-card"><strong>256-bit SSL</strong><span>Encrypted ledger</span></div>
        <div class="auth-mini-card"><strong>RBI TReDS</strong><span>Direct gateway</span></div>
        <div class="auth-mini-card"><strong>MSMED Act 2006</strong><span>Section 15/16 enforced</span></div>
    </div>
</x-layouts.auth>
