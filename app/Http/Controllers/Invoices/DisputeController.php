<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreDisputeRequest;
use App\Models\Invoice;
use App\Services\InvoiceWorkflow;
use Illuminate\Http\RedirectResponse;

/**
 * Filing a delayed-payment claim.
 *
 * Goes straight to the evidence packet afterwards: the next thing a supplier
 * needs after choosing a forum is the deadline and the documents.
 */
class DisputeController extends Controller
{
    public function store(StoreDisputeRequest $request, Invoice $invoice, InvoiceWorkflow $workflow): RedirectResponse
    {
        $workflow->startDispute($invoice, ['forum' => $request->forum()]);

        return redirect()
            ->route('invoices.claim', $invoice)
            ->with('status', $request->forum()->shortLabel().' claim opened. The filing deadline below is computed from the invoice due date.');
    }
}
