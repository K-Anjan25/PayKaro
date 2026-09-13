<x-layouts.auth title="Create account" copy="Create an account, add a buyer, and raise your first invoice in minutes. Your receivables, interest and evidence — all in one pipeline.">
    <h1 class="auth-h">Create your account</h1>
    <p class="auth-sub">Set up your business and you're in. Each user sees only their own business's receivables.</p>

    @if (session('status'))
        <div class="auth-err">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="auth-err">{{ $errors->first() }}</div>
    @endif

    <x-auth.google-button label="Sign up with Google" />
    @if (\App\Support\GoogleSignIn::enabled())
        <div class="auth-or">or</div>
    @endif

    <form method="post" action="{{ route('register') }}">
        @csrf
        <label class="auth-lbl" for="business_name">Business name</label>
        <input class="auth-in" id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" placeholder="Your company Pvt Ltd">
        @error('business_name')<div class="auth-err">{{ $message }}</div>@enderror

        <label class="auth-lbl" for="name">Your name</label>
        <input class="auth-in" id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Full name" required autofocus>
        @error('name')<div class="auth-err">{{ $message }}</div>@enderror

        <label class="auth-lbl" for="email">Work email</label>
        <input class="auth-in" id="email" type="email" name="email" value="{{ old('email') }}" placeholder="you@company.in" required autocomplete="email">
        @error('email')<div class="auth-err">{{ $message }}</div>@enderror

        <label class="auth-lbl" for="password">Password</label>
        <input class="auth-in" id="password" type="password" name="password" placeholder="Create a password" required minlength="8" autocomplete="new-password">
        @error('password')<div class="auth-err">{{ $message }}</div>@enderror

        <button class="auth-cta" type="submit">Create account →</button>
    </form>

    <div class="auth-alt">Already have an account? <a href="{{ route('login') }}">Sign in</a></div>
</x-layouts.auth>
