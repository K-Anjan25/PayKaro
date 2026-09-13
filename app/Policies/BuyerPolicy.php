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

    public function update(User $user, Buyer $buyer): bool
    {
        return $user->businessId() === (int) $buyer->business_id && $user->role->canWrite();
    }
}
