<?php

namespace App\Http\Controllers\Invoices;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateEvidenceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceStatusRequest;
use App\Models\Buyer;
use App\Models\Invoice;
use App\Services\ClaimPacket;
use App\Services\Dashboard;
use App\Services\InvoiceWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The invoice pipeline: raise, review, move, evidence, claim.
 *
 * Route-model binding hands these actions an `Invoice` the tenant scope has
 * already filtered, so opening a URL for another business's invoice is a 404 —
 * the same answer as "does not exist", which is what the spec asked for: not a
 * 403, not data.
 */
class InvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $invoices = Invoice::query()
            ->withMetrics()
            ->ofStatus($request->query('status'))
            ->forBuyer($request->integer('buyer') ?: null)
            ->search($request->query('q'))
            ->latestFirst()
            ->paginate(15)
            ->withQueryString();

        $summary = app(Dashboard::class)->overview();
        $financedBook = Invoice::query()
            ->withMetrics()
            ->where('status', InvoiceStatus::Financed->value)
            ->get();

        return view('invoices.index', [
            'invoices' => $invoices,
            'status' => (string) $request->query('status', 'all'),
            'buyers' => Buyer::query()->ordered()->get(['id', 'name']),
            'summary' => $summary,
            'financedAmount' => round($financedBook->sum(fn (Invoice $invoice) => $invoice->balance()), 2),
            'financedCount' => $financedBook->count(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Invoice::class);

        return view('invoices.form', [
            'invoice' => null,
            'buyers' => Buyer::query()->ordered()->get(),
            'suggestedNumber' => 'INV-'.now()->format('Y').'-'.random_int(1000, 9999),
            'invoiceDate' => now()->toDateString(),
        ]);
    }

    public function store(StoreInvoiceRequest $request, InvoiceWorkflow $workflow): RedirectResponse
    {
        $invoice = $workflow->create($request->validated());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', sprintf(
                'Invoice %s raised. Its due date is set by the %d-day MSME window.',
                $invoice->number,
                config('paykaro.msme_due_days'),
            ));
    }

    public function show(Invoice $invoice): View
    {
        $this->authorize('view', $invoice);

        $invoice->load([
            'buyer',
            'evidences',
            'payments' => fn ($query) => $query->latest('paid_on')->latest('id'),
            'financings' => fn ($query) => $query->latest('disbursed_on')->latest('id'),
            'disputes' => fn ($query) => $query->latest('filed_on')->latest('id'),
        ]);

        return view('invoices.show', [
            'invoice' => $invoice,
            'checklist' => Invoice::sortChecklist($invoice->evidences),
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        $this->authorize('update', $invoice);

        return view('invoices.form', [
            'invoice' => $invoice,
            'buyers' => Buyer::query()->ordered()->get(),
            'suggestedNumber' => $invoice->number,
            'invoiceDate' => $invoice->invoice_date?->toDateString() ?? now()->toDateString(),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, InvoiceWorkflow $workflow): RedirectResponse
    {
        $workflow->update($invoice, $request->validated());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', 'Invoice updated. Due date and totals were recomputed from the invoice date.');
    }

    public function updateStatus(UpdateInvoiceStatusRequest $request, Invoice $invoice, InvoiceWorkflow $workflow): RedirectResponse
    {
        $workflow->setStatus($invoice, $request->status());

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('status', 'Status set to '.$request->status()->label().'.');
    }

    /**
     * Tick or untick one evidence line.
     *
     * The readiness score, the finance queue and the claim packet all read this
     * checklist, so one write moves all three — which is the point of keeping
     * evidence as a record rather than a checkbox.
     */
    public function updateEvidence(UpdateEvidenceRequest $request, Invoice $invoice, InvoiceWorkflow $workflow): RedirectResponse
    {
        $workflow->setEvidence($invoice, $request->type(), $request->present());

        return redirect()->route('invoices.show', $invoice);
    }

    public function claim(Invoice $invoice, ClaimPacket $packet): View
    {
        $this->authorize('view', $invoice);

        $invoice->load(['buyer', 'disputes', 'evidences', 'payments']);

        return view('invoices.claim', [
            'invoice' => $invoice,
            'rows' => $packet->rows($invoice),
            'missing' => $packet->missingCount($invoice),
        ]);
    }
}
