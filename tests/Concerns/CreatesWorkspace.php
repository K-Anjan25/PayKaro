<?php

namespace Tests\Concerns;

use App\Enums\TredsOnboarding;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Buyer;
use App\Models\Invoice;
use App\Models\User;

/**
 * The shape every workspace test needs: a business (tenant), a member of it, and
 * records created *as* that member — because the tenant is resolved from the
 * authenticated user, never from the request.
 */
trait CreatesWorkspace
{
    protected function workspace(UserRole $role = UserRole::Owner): User
    {
        $business = Business::factory()->create();

        $user = User::factory()->for($business)->create(['role' => $role]);

        $this->actingAs($user);

        return $user;
    }

    protected function buyerAs(User $user, array $attributes = []): Buyer
    {
        $this->actingAs($user);

        return Buyer::factory()->create($attributes);
    }

    /**
     * A buyer onboarded on TReDS, so the invoice can be finance-ready.
     */
    protected function tredsBuyer(User $user): Buyer
    {
        return $this->buyer($user, ['treds_onboarded' => TredsOnboarding::Yes]);
    }

    protected function invoice(User $user, Buyer $buyer, array $attributes = []): Invoice
    {
        $this->actingAs($user);

        return Invoice::factory()->forBuyer($buyer)->create($attributes);
    }

    /**
     * The other business in the room — the one whose data must never leak.
     */
    protected function otherWorkspace(): User
    {
        $business = Business::factory()->create();

        return User::factory()->for($business)->create();
    }
}
