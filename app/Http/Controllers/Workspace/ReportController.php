<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Services\Dashboard;
use Illuminate\View\View;

/**
 * Cash-flow reporting: outstanding by buyer, and the next-30-day picture.
 */
class ReportController extends Controller
{
    public function index(Dashboard $dashboard): View
    {
        return view('workspace.reports', [
            'summary' => $dashboard->overview(),
            'byBuyer' => $dashboard->outstandingByBuyer(),
        ]);
    }
}
