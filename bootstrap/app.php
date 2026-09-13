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
