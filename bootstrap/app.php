<?php

use App\Tenancy\Exceptions\TenantNotResolved;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Guests land on the workspace sign-in page; signed-in users never see
        // the auth pages again. Both are the legacy entry points, so the URLs
        // of an upgraded workspace keep working.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        /*
         * TLS terminates at the proxy on every PaaS (Railway, Render, Fly, Cloud
         * Run, DigitalOcean App Platform, Vercel) and on Cloudflare, an ALB or a
         * load balancer in front of a VPS. Without trusting that proxy Laravel
         * sees plain HTTP and generates `http://` URLs — and with
         * SESSION_SECURE_COOKIE=true the session cookie can fail to be set.
         *
         * Off unless TRUSTED_PROXIES is set, because `*` in front of a directly
         * exposed app would let a client spoof X-Forwarded-For. Set it to `*`
         * only where something always sits in front, or to the proxy's CIDR.
         *
         * Read from the environment rather than a config key on purpose: every
         * target in DEPLOY.md supplies it as a real process environment variable
         * (Docker `-e`, a PaaS dashboard, systemd), so it is available here even
         * when the config is cached and `.env` is not re-read.
         */
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(
                at: $proxies === '*' ? '*' : array_map('trim', explode(',', (string) $proxies)),
            );
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Tenant isolation is fail-closed. If a tenant-scoped query is reached
        // without a resolved business, answer 403 and say nothing about data —
        // never render a page that might belong to another business.
        $exceptions->renderable(function (TenantNotResolved $e, Request $request) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No workspace resolved for this request.'], 403)
                : response()->view('errors.403', [], 403);
        });
    })->create();
