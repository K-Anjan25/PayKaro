<?php

namespace App\Http\Controllers\Buyers;

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
