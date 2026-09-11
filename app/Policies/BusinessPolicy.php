<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

/**
 * Business identity — GSTIN, PAN, Udyam and bank details — is owner-only: those
 * are the details settled money is paid against and the ones a claim cites.
 */
class BusinessPolicy
{
    public function update(User $user, Business $business): bool
    {
        return $user->businessId() === $business->id && $user->role->canManageBusiness();
    }
}
