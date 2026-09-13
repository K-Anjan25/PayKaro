<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateBusinessRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The tenant's own identity: GSTIN, PAN, Udyam, bank details, TReDS flag.
 *
 * There is deliberately no `/business/{id}` route — the business you edit is
 * always the one on your session, so a business id is never an input that could
 * be tampered with. Owner-only enforcement lives in `BusinessPolicy`, reached
 * from the form request's `authorize()`.
 */
class SettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('workspace.settings', [
            'business' => $request->user()->business,
        ]);
    }

    public function update(UpdateBusinessRequest $request): RedirectResponse
    {
        $request->user()->business->update($request->validated());

        return redirect()
            ->route('settings.edit')
            ->with('status', 'Business details saved.');
    }
}
