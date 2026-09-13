<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AccountProvisioner;
use App\Support\GoogleSignIn;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use RuntimeException;
use Throwable;

/**
 * Google Sign-In (OAuth 2.0 authorization code, via Laravel Socialite).
 *
 * The legacy app hand-rolled this: a one-time state row in `oauth_states`, a
 * curl-shaped token exchange, a profile fetch. Socialite does all of it — and
 * the CSRF state now rides the session instead of a table, which is why that
 * table is gone.
 *
 * One-click provisioning order (App\Services\AccountProvisioner) is unchanged:
 * match on google_id, else link an account with the same email, else create a
 * business and its owner.
 */
class GoogleOAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(GoogleSignIn::enabled(), 404);

        return $this->driver($request)->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(GoogleSignIn::enabled(), 404);

        try {
            $googleUser = $this->driver($request)->user();
        } catch (Throwable $e) {
            // A denied consent screen, an expired code or a replayed state all
            // land here. Report the reason, never the details.
            report($e);

            return redirect()
                ->route('login')
                ->with('status', "Google sign-in didn't complete. Try again, or use email and password.");
        }

        try {
            $user = app(AccountProvisioner::class)->findOrCreateFromGoogle([
                'id' => (string) $googleUser->getId(),
                'email' => (string) $googleUser->getEmail(),
                'name' => (string) $googleUser->getName(),
                'avatar' => (string) $googleUser->getAvatar(),
            ]);
        } catch (RuntimeException $e) {
            // Google answered without a usable profile (an email-less consent,
            // for instance). Only this failure mode is swallowed: a database
            // problem further down must still surface as a server error.
            report($e);

            return redirect()
                ->route('login')
                ->with('status', 'Google did not share an email address we can sign you in with.');
        }

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * The Socialite driver, pinned to this deployment's callback URI.
     */
    protected function driver(Request $request): GoogleProvider
    {
        return Socialite::driver('google')->redirectUrl(GoogleSignIn::redirectUri($request));
    }
}
