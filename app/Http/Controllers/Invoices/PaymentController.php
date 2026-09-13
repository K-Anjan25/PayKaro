<?php

namespace App\Http\Controllers\Invoices;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StorePaymentRequest;
use App\Models\Invoice;
use App\Services\InvoiceWorkflow;
use Illuminate\Http\RedirectResponse;

/**
 * Recording money received against an invoice.
 */
class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Invoice $invoice, InvoiceWorkflow $workflow): RedirectResponse
    {
        $payment = $workflow->recordPayment($invoice, $request->validated());

        if ($payment === null) {
            return redirect()
                ->route('invoices.show', $invoice)
                ->with('status', 'Nothing is outstanding on this invoice, so the payment was not recorded.');
        }

        $invoice->refresh();

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', sprintf(
                'Payment of %s recorded.%s',
                money($payment->amount),
                $invoice->status === InvoiceStatus::Settled ? ' The balance is clear, so the invoice is settled.' : '',
            ));
    }
}
