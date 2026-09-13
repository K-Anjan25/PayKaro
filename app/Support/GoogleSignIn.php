<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Whether Google Sign-In is switched on for this deployment.
 *
 * Configuration-driven exactly as the flat-PHP app was: with no client id and
 * secret, the button is not rendered at all and the routes answer 404, rather
 * than showing a control that can only fail.
 */
final class GoogleSignIn
{
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    /**
     * The callback URI registered with Google.
     *
     * Falls back to the generated route when GOOGLE_REDIRECT_URI is unset, which
     * is what lets a tunneled or preview host complete the flow without editing
     * the Cloud Console first.
     */
    public static function redirectUri(Request $request): string
    {
        $configured = config('services.google.redirect');

        return filled($configured)
            ? (string) $configured
            : route('auth.google.callback', absolute: true);
    }
}
