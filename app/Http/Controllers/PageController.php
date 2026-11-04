<?php

namespace App\Http\Controllers;

use App\Support\Legal;
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
     * The legal set. Content lives in App\Support\Legal as data — three pages
     * with one layout, so a rate change or a new stored field is a single,
     * reviewable edit instead of a copy-paste sweep across templates.
     *
     * Each Legal::*() array is shaped exactly as <x-legal-layout>'s props, so the
     * same data drives all three pages: the keys *are* the props.
     *
     * @return array{title: string, lede: string, sections: list<array<string, mixed>>}
     */
    public function terms(): View
    {
        return view('components.legal-layout', Legal::terms());
    }

    public function privacy(): View
    {
        return view('components.legal-layout', Legal::privacy());
    }

    public function security(): View
    {
        return view('components.legal-layout', Legal::security());
    }

    public function article(string $slug): View|RedirectResponse
    {
        $article = News::find($slug);

        if ($article === null) {
            // Same anchor the /news alias uses — and built the same way, so the two
            // cannot drift into one-with-a-slash and one-without.
            return redirect('/#news');
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
