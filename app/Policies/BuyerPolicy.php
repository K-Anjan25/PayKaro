<?php

namespace App\Policies;

use App\Models\Buyer;
use App\Models\User;

class BuyerPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->role->canWrite();
    }

    /**
     * The buyer's own page: same tenant test as every other read, stated here
     * because a buyer is reached by id in a URL and the policy is the last line of
     * defence if a query is ever written outside the tenant scope.
     */
    public function view(User $user, Buyer $buyer): bool
    {
        return $user->businessId() === (int) $buyer->business_id;
    }

    public function update(User $user, Buyer $buyer): bool
    {
        return $user->businessId() === (int) $buyer->business_id && $user->role->canWrite();
    }
}
