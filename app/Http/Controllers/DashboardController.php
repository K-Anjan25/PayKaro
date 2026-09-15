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
        $summary = $dashboard->overview();
        $queue = $dashboard->financeQueue();
        $readyQueue = $queue->filter(fn (Invoice $invoice) => $invoice->isFinanceReady())->values();

        return view('dashboard', [
            'summary' => $summary,
            'recent' => Invoice::query()->withMetrics()->latestFirst()->take(5)->get(),
            'alerts' => Alert::query()
                ->unread()
                ->with('invoice:id,number')
                ->latest('id')
                ->limit((int) config('paykaro.alert_limit'))
                ->get(),
            'readyQueue' => $readyQueue,
            'readyAmount' => round($readyQueue->sum(fn (Invoice $invoice) => $invoice->balance()), 2),
            'indicativeDiscountRate' => round((float) config('paykaro.bank_rate') + 1.35, 2),
        ]);
    }
}
