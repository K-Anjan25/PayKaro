<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

/**
 * Dismissing the dashboard's "Needs attention" list hides it from the whole
 * business, so it is a write.
 */
class AlertPolicy
{
    public function markRead(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->businessId() === (int) $alert->business_id;
    }
}
