@props(['label' => 'Continue with Google'])

{{--
    Rendered only when Google credentials are configured, so a deployment without
    OAuth never shows a button that can only fail.
--}}
@if (\App\Support\GoogleSignIn::enabled())
    <a class="auth-google" href="{{ route('auth.google') }}">
        <span aria-hidden="true" style="display:inline-flex;width:1.05rem;height:1.05rem;flex-shrink:0;">
            <svg viewBox="0 0 48 48" width="20" height="20">
                <path fill="#4285F4" d="M45.12 24.5c0-1.56-.14-3.06-.4-4.5H24v8.51h11.84a10.1 10.1 0 0 1-4.39 6.62v5.5h7.1c4.16-3.83 6.57-9.47 6.57-16.13z"/>
                <path fill="#34A853" d="M24 46c5.94 0 10.92-1.97 14.56-5.33l-7.1-5.5c-1.97 1.32-4.49 2.1-7.46 2.1-5.73 0-10.58-3.87-12.31-9.07H4.34v5.67A21.98 21.98 0 0 0 24 46z"/>
                <path fill="#FBBC05" d="M11.69 28.2a13.2 13.2 0 0 1 0-8.4v-5.67H4.34a21.98 21.98 0 0 0 0 19.74l7.35-5.67z"/>
                <path fill="#EA4335" d="M24 10.75c3.23 0 6.12 1.11 8.4 3.29l6.3-6.3A21.96 21.96 0 0 0 24 2a21.98 21.98 0 0 0-19.66 12.13l7.35 5.67C13.42 14.62 18.27 10.75 24 10.75z"/>
            </svg>
        </span>
        {{ $label }}
    </a>
@endif
