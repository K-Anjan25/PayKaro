<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

/**
 * Who may touch a receivable.
 *
 * Two independent checks, on purpose:
 *  - the tenant check is *redundant* with the global scope, and stays because a
 *    policy is the last line of defence if a query is ever written outside the
 *    scope (a queue job, a console command, a future API);
 *  - the role check makes a viewer genuinely read-only instead of merely
 *    "not the owner" — legacy PayKaro showed every mutation to everyone.
 */
class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $this->owns($user, $invoice);
    }

    public function create(User $user): bool
    {
        return $user->role->canWrite();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->owns($user, $invoice) && $user->role->canWrite();
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function finance(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function dispute(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function toggleEvidence(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    protected function owns(User $user, Invoice $invoice): bool
    {
        return $user->businessId() === (int) $invoice->business_id;
    }
}
