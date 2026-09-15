<?php

namespace App\Http\Controllers\Buyers;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Buyers\StoreBuyerRequest;
use App\Models\Buyer;
use App\Models\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The customer book.
 *
 * Outstanding is summed from the open invoices rather than stored on the buyer,
 * so it is never wrong the morning after a payment is recorded. The group-by
 * keeps it to one query for the whole page.
 */
class BuyerController extends Controller
{
    public function index(): View
    {
        return view('buyers.index', [
            'buyers' => Buyer::query()->ordered()->get(),
            'outstanding' => Invoice::query()
                ->open()
                ->selectRaw('buyer_id, SUM(total_amount) as outstanding')
                ->groupBy('buyer_id')
                ->pluck('outstanding', 'buyer_id'),
        ]);
    }

    /**
     * One buyer, and everything owed by them.
     *
     * The design package drew this screen twice (desktop and mobile) and neither
     * existed: the list showed an outstanding figure that a member could not drill
     * into, so "why is Orbit Auto at ₹1.4L" had no answer inside the product. The
     * page answers it with their invoices, the interest already accrued on them, and
     * what their TReDS status means for discounting — the three things a supplier
     * opens a buyer record to find.
     */
    public function show(Buyer $buyer): View
    {
        $this->authorize('view', $buyer);

        $invoices = $buyer->invoices()
            ->with('financings', 'disputes')
            ->withMetrics()
            ->latestFirst()
            ->get();

        // "Open" is the same rule `Buyer::openInvoices()` uses — not settled and not
        // draft — so this page and the list it was opened from never disagree about
        // what the buyer owes. A disputed invoice is still owed, so it stays here.
        $open = $invoices->reject(fn (Invoice $invoice) => in_array(
            $invoice->status,
            [InvoiceStatus::Settled, InvoiceStatus::Draft],
            true,
        ));

        return view('buyers.show', [
            'buyer' => $buyer,
            'invoices' => $invoices,
            // Open value is the face value still owed; interest is what the statute
            // has added on top, and it is the number a claim would quote.
            'outstanding' => round((float) $open->sum(fn (Invoice $invoice) => $invoice->balance()), 2),
            'interest' => round((float) $open->sum(fn (Invoice $invoice) => $invoice->interest()), 2),
            // `sortByDesc('overdueDays')` would read an attribute that does not
            // exist — the days are derived — so it sorts on the call.
            'overdue' => $open
                ->filter(fn (Invoice $invoice) => $invoice->overdueDays() > 0)
                ->sortByDesc(fn (Invoice $invoice) => $invoice->overdueDays())
                ->values(),
            'closed' => $invoices->filter(fn (Invoice $invoice) => $invoice->status->isClosed())->values(),
            'readiness' => $open->isEmpty() ? null : (int) round($open->avg(fn (Invoice $invoice) => $invoice->readiness())),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Buyer::class);

        return view('buyers.form');
    }

    public function store(StoreBuyerRequest $request): RedirectResponse
    {
        $buyer = Buyer::create($request->validated());

        return redirect()
            ->route('buyers.index')
            ->with('status', sprintf(
                '%s added. Their TReDS status is %s, which decides whether their invoices can be discounted.',
                $buyer->name,
                strtolower($buyer->treds_onboarded->label()),
            ));
    }
}
