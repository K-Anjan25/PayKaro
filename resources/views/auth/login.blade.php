<x-layouts.auth title="Sign in" copy="Sign in to monitor statutory receivables, TReDS bidding and compliance ledgers.">
    <div class="screen-kicker">Institutional access</div>
    <h1 class="auth-h">Welcome back</h1>
    <p class="auth-sub">Sign in to monitor statutory receivables, TReDS bidding and compliance ledgers.</p>

    @if (session('status'))
        <div class="auth-err">{{ session('status') }}</div>
    @endif

    @error('email')
        <div class="auth-err">{{ $message }}</div>
    @enderror
    @error('password')
        <div class="auth-err">{{ $message }}</div>
    @enderror

    <div class="auth-mini-grid">
        <div class="auth-mini-card"><strong>MSMED Act</strong><span>45-day clock</span></div>
        <div class="auth-mini-card"><strong>TReDS API</strong><span>Live bids</span></div>
        <div class="auth-mini-card"><strong>Samadhaan</strong><span>Auto docket</span></div>
    </div>

    <x-auth.google-button label="Sign in with Google" />
    @if (\App\Support\GoogleSignIn::enabled())
        <div class="auth-or">or continue with email</div>
    @endif

    <form method="post" action="{{ route('login') }}">
        @csrf
        <label class="auth-lbl" for="email">Email</label>
        <input class="auth-in" id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="e.g. sunita@shreeprecision.in" autofocus autocomplete="username">

        <label class="auth-lbl" for="password">Password</label>
        <input class="auth-in" id="password" type="password" name="password" required placeholder="••••••••" autocomplete="current-password">

        <label style="display:flex;gap:.45rem;align-items:center;margin:.55rem 0 1rem;font-size:.84rem;color:var(--n-ink-mute);">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            Remember me on this enterprise terminal
        </label>

        <button class="auth-cta" type="submit">Sign in →</button>
    </form>

    <div class="auth-alt">Don't have an account? <a href="{{ route('register') }}">Sign up</a></div>

    @if (config('paykaro.demo'))
        <div class="support-card support-card--inline" style="margin-top:1.2rem;">
            <span>Demo access</span>
            <strong>sunita@shreeprecision.in / demo1234</strong>
        </div>
    @endif
</x-layouts.auth>
