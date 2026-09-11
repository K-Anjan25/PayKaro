<x-layouts.auth title="Sign in">
    <h1 class="auth-h">Welcome back</h1>
    <p class="auth-sub">Sign in to your workspace. Each user sees only their own business's receivables.</p>

    @if (session('status'))
        <div class="auth-err">{{ session('status') }}</div>
    @endif

    @error('email')
        <div class="auth-err">{{ $message }}</div>
    @enderror
    @error('password')
        <div class="auth-err">{{ $message }}</div>
    @enderror

    <x-auth.google-button />
    @if (\App\Support\GoogleSignIn::enabled())
        <div class="auth-or">or</div>
    @endif

    <form method="post" action="{{ route('login') }}">
        @csrf
        <label class="auth-lbl" for="email">Email</label>
        <input class="auth-in" id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="you@company.in" autofocus autocomplete="username">

        <label class="auth-lbl" for="password">Password</label>
        <input class="auth-in" id="password" type="password" name="password" required placeholder="••••••••" autocomplete="current-password">

        <label style="display:flex;gap:.45rem;align-items:center;margin:.55rem 0 .2rem;font-size:.8rem;color:var(--n-ink-mute);">
            <input type="checkbox" name="remember" value="1" @checked(old('remember'))>
            Keep me signed in for a week
        </label>

        <button class="auth-cta" type="submit">Sign in →</button>
    </form>

    <div class="auth-alt">New to {{ config('app.name') }}? <a href="{{ route('register') }}">Create an account</a></div>

    @if (config('paykaro.demo'))
        <div class="auth-alt" style="margin-top:.9rem;line-height:1.7;">
            Demo logins (password <strong>demo1234</strong>):<br>
            <a href="#" onclick="document.getElementById('email').value='sunita@shreeprecision.in';document.getElementById('password').value='demo1234';return false;">sunita@shreeprecision.in</a> ·
            <a href="#" onclick="document.getElementById('email').value='farhan@metrowceramics.in';document.getElementById('password').value='demo1234';return false;">farhan@metrowceramics.in</a>
        </div>
    @endif
</x-layouts.auth>
