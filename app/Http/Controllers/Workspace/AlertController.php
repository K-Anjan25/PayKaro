<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Dismissing the dashboard's attention list.
 *
 * Marks read rather than deleting: the list is shared by the whole business, and
 * "we saw that on the 14th" is worth keeping.
 */
class AlertController extends Controller
{
    public function markRead(Request $request): RedirectResponse
    {
        Gate::authorize('markRead', Alert::class);

        $count = Alert::query()->unread()->update(['read_at' => now()]);

        return redirect()
            ->route('dashboard')
            ->with('status', $count > 0
                ? "Dismissed {$count} attention ".($count === 1 ? 'item' : 'items').'. New ones will appear as invoices age.'
                : 'Nothing to dismiss.');
    }
}
