<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\Invoice;
use App\Services\Dashboard;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The overview: what is owed, what is overdue, what needs attention.
     */
    public function index(Request $request, Dashboard $dashboard): View
    {
        return view('dashboard', [
            'summary' => $dashboard->overview(),
            'recent' => Invoice::query()->withMetrics()->latestFirst()->take(5)->get(),
            'alerts' => Alert::query()
                ->unread()
                ->with('invoice:id,number')
                ->latest('id')
                ->limit((int) config('paykaro.alert_limit'))
                ->get(),
        ]);
    }
}
