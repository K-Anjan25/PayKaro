<?php

use App\Models\Business;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Str;

if (! function_exists('tenant')) {
    /**
     * The Business whose data the current request is allowed to see.
     *
     * Resolved from the authenticated user, so it can never drift away from
     * the session. Null on guest routes (landing, pricing, news) and in
     * console runs until a seeder or command binds one explicitly.
     */
    function tenant(): ?Business
    {
        return app(TenantContext::class)->business();
    }
}

if (! function_exists('tenant_id')) {
    function tenant_id(): ?int
    {
        return app(TenantContext::class)->id();
    }
}

if (! function_exists('money')) {
    /**
     * Format an amount as Indian-grouped rupees, e.g. 1234567 -> "₹12,34,567".
     *
     * Plain text on purpose: it is used inside {{ }} (escaped) and in the
     * claim packet, and must never carry markup.
     */
    function money(float|int|string|null $amount): string
    {
        return Money::inr($amount);
    }
}

if (! function_exists('money_compact')) {
    /**
     * Short rupee form for chart axes (₹2.5L, ₹40K) — same rule as money(), just
     * compressed, so the y-axis and the bar labels can never disagree.
     */
    function money_compact(float|int|string|null $amount): string
    {
        return Money::compact($amount);
    }
}

if (! function_exists('plural')) {
    /**
     * "3 invoices" / "1 invoice", count and noun together.
     *
     * A helper rather than a `Str::` call in the views: Blade templates compile to
     * plain PHP with no imports, and a Laravel 12 app has no facade aliases
     * registered by default, so a template must not reach for `Str::`.
     */
    function plural(int|float|string|null $count, string $singular, ?string $plural = null): string
    {
        $total = (int) $count;

        $word = $total === 1
            ? $singular
            : ($plural ?? Str::plural($singular));

        return $total.' '.$word;
    }
}
