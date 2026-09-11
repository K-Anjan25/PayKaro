<?php

namespace App\Providers;

use App\Services\Receivables;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bind the two things the domain is built on.
     *
     * Receivables is a singleton because it is pure arithmetic seeded from
     * config — one instance, read everywhere, and in tests it can be replaced
     * with a hand-built one to pin a rate or a due window.
     *
     * TenantContext is *scoped* rather than a plain singleton: it holds the
     * current business, so under a persistent runtime (Octane, queue workers) it
     * must reset between requests/scope iterations or one workspace's tenant
     * would leak into another's.
     */
    public function register(): void
    {
        $this->app->singleton(Receivables::class, fn () => Receivables::fromConfig());

        $this->app->scoped(TenantContext::class, fn ($app) => new TenantContext(
            $app->make(AuthFactory::class),
        ));
    }

    public function boot(): void
    {
        // Paginated tables render the workspace's own pagination markup
        // (resources/views/components/pagination.blade.php) rather than a
        // bundled Tailwind or Bootstrap partial.
        Paginator::defaultView('components.pagination');
        Paginator::defaultSimpleView('components.pagination');
    }
}
