<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Services\Dashboard;
use Illuminate\View\View;

/**
 * The TReDS queue: what can be financed today, and what one missing document or
 * un-onboarded buyer is holding back.
 */
class FinanceQueueController extends Controller
{
    public function index(Dashboard $dashboard): View
    {
        $queue = $dashboard->financeQueue();

        return view('workspace.finance-queue', [
            'queue' => $queue,
            'ready' => $queue->filter(fn ($invoice) => $invoice->treds()->isFinanceReady()),
            'blocked' => $queue->reject(fn ($invoice) => $invoice->treds()->isFinanceReady()),
            'disbursable' => $queue->sum(fn ($invoice) => $invoice->isFinanceReady() ? $invoice->balance() : 0.0),
        ]);
    }
}
