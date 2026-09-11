<?php

namespace App\Http\Controllers;

use App\Support\News;
use App\Support\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The public marketing pages, plus the legacy URLs.
 *
 * The old app addressed invoices as `/invoice?id=5` and claims as `/claim?id=5`.
 * Those are now `/invoices/5` and `/invoices/5/claim`, and the old shapes are
 * redirected rather than dropped: a workspace link pasted into a WhatsApp thread
 * a month ago still resolves.
 */
class PageController extends Controller
{
    public function pricing(): View
    {
        return view('marketing.pricing', [
            'plans' => Pricing::plans(),
        ]);
    }

    public function help(): View
    {
        return view('marketing.help');
    }

    public function contact(): View
    {
        return view('marketing.contact');
    }

    /**
     * `/terms`, `/privacy` and `/security` were aliases into the help page; kept
     * as aliases so footer links from the old pages still land somewhere.
     */
    public function legal(): RedirectResponse
    {
        return redirect()->route('help');
    }

    public function article(string $slug): View|RedirectResponse
    {
        $article = News::find($slug);

        if ($article === null) {
            return redirect(route('landing').'#news');
        }

        return view('marketing.article', ['article' => $article]);
    }

    /**
     * Legacy `/invoice?id=N` (or `?edit=N`) into the RESTful route.
     */
    public function legacyInvoice(Request $request): RedirectResponse
    {
        $id = (int) ($request->integer('id') ?: $request->integer('edit'));

        if ($id <= 0) {
            return redirect()->route('invoices.index');
        }

        return $request->has('edit')
            ? redirect()->route('invoices.edit', $id)
            : redirect()->route('invoices.show', $id);
    }

    public function legacyClaim(Request $request): RedirectResponse
    {
        $id = $request->integer('id');

        return $id > 0
            ? redirect()->route('invoices.claim', $id)
            : redirect()->route('invoices.index');
    }
}
