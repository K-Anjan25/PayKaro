<?php

namespace App\Tenancy;

use App\Models\Business;
use App\Models\User;
use App\Tenancy\Exceptions\TenantNotResolved;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Holds the Business (tenant) that every tenant-scoped query is bound to.
 *
 * Resolution order:
 *  1. an explicitly bound business — set by `identify()` or `run()` (seeders,
 *     console commands, tests);
 *  2. the authenticated user's business.
 *
 * Deriving the tenant from the *user* rather than from the request means the
 * data layer and the UI can never disagree: there is no path where a query
 * runs against a business id that came from user input. When neither is
 * available, `require()` throws instead of quietly returning everything.
 */
class TenantContext
{
    protected ?Business $business = null;

    protected bool $bound = false;

    /**
     * Which user `business` was resolved for. Memoising without this key would
     * keep the previous tenant alive if the authenticated user changed.
     */
    protected ?int $resolvedForUserId = null;

    public function __construct(protected AuthFactory $auth) {}

    /**
     * Bind a business as the current tenant for the rest of the process.
     */
    public function identify(?Business $business): static
    {
        $this->business = $business;
        $this->bound = true;

        return $this;
    }

    /**
     * Run a callback with a different tenant bound, restoring the previous one.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Business $business, Closure $callback): mixed
    {
        $previous = $this->business;
        $wasBound = $this->bound;

        $this->identify($business);

        try {
            return $callback();
        } finally {
            $this->business = $previous;
            $this->bound = $wasBound;
            $this->resolvedForUserId = null;
        }
    }

    /**
     * Forget any bound tenant and fall back to the authenticated user.
     */
    public function forget(): void
    {
        $this->business = null;
        $this->bound = false;
        $this->resolvedForUserId = null;
    }

    public function business(): ?Business
    {
        if ($this->bound) {
            return $this->business;
        }

        $user = $this->auth->guard()->user();

        if ($user === null) {
            $this->business = null;
            $this->resolvedForUserId = null;

            return null;
        }

        if ($this->resolvedForUserId !== $user->getAuthIdentifier()) {
            $this->resolvedForUserId = (int) $user->getAuthIdentifier();
            $this->business = $user instanceof User ? $user->business : null;
        }

        return $this->business;
    }

    public function id(): ?int
    {
        return $this->business()?->id;
    }

    public function check(): bool
    {
        return $this->business() !== null;
    }

    /**
     * The tenant id every scoped query must have. Throws when there is none.
     *
     * @throws TenantNotResolved
     */
    public function requireId(): int
    {
        $id = $this->id();

        if ($id === null) {
            throw new TenantNotResolved(
                'A tenant-scoped model was queried without a resolved business. '
                .'Authenticate, or bind one with tenant()->identify($business) in a seeder or command.'
            );
        }

        return $id;
    }

}
