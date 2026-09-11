<?php

namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreFinancingRequest;
use App\Models\Invoice;
use App\Services\InvoiceWorkflow;
use Illuminate\Http\RedirectResponse;

/**
 * Recording a TReDS disbursal against an invoice.
 */
class FinancingController extends Controller
{
    public function store(StoreFinancingRequest $request, Invoice $invoice, InvoiceWorkflow $workflow): RedirectResponse
    {
        $financing = $workflow->recordFinancing($invoice, $request->validated());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', sprintf(
                '%s disbursed by %s — the invoice is now financed.',
                money($financing->amount_disbursed),
                $financing->financier,
            ));
    }
}
