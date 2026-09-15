<?php

namespace App\Http\Controllers;

use App\Support\News;
use App\Support\Proof;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * `/` — the landing page for visitors, the workspace overview for members.
 *
 * Legacy PayKaro rendered both on one entry point. Here a signed-in visitor is
 * redirected to the dashboard instead: one route owns the overview's data, and
 * `/` keeps working for every bookmark and shared link either way.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return view('marketing.landing', [
            'news' => News::all(),
            // The only figures the page may publish, computed from the config and
            // enums the domain uses (BRAND_PLAN §4).
            'proof' => Proof::claims(),
            'limits' => Proof::limits(),
        ]);
    }
}
