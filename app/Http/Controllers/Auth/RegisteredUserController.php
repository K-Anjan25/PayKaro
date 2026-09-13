<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AccountProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Sign-up: create a business (tenant) and its owner, then sign them straight in.
 *
 * A new workspace deliberately starts empty. The demo data belongs to the two
 * seeded businesses, and copying a demo book into a real supplier's tenant would
 * make their first dashboard a lie.
 */
class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.signup');
    }

    public function store(RegisterRequest $request, AccountProvisioner $provisioner): RedirectResponse
    {
        $user = $provisioner->register($request->validated());

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('buyers.create')->with('status', sprintf(
            'Welcome, %s. %s is set up — add your first buyer and you can raise an invoice.',
            $user->name,
            $user->business->name,
        ));
    }
}
