<?php

namespace App\Brand;

use App\Enums\EvidenceType;
use App\Enums\TredsOnboarding;
use App\Models\Buyer;
use App\Models\Invoice;

/**
 * The first-run checklist: the four things a new workspace has to do, and whether
 * it has done them.
 *
 * `WIREFRAME_AUDIT.md` §6 and `WIREFRAMES.md` both record the same gap — "Sign-up
 * lands straight on the dashboard", with no walkthrough anywhere in the product.
 * The design package drew a feature tour; this is the honest version of it for a
 * server-rendered app: a card that states the next step, because it reads the
 * workspace's own state rather than asking anyone to click through slides.
 *
 * It disappears when there is nothing left to say — no card once every step is
 * done — so it is an aid, not permanent chrome. The steps are derived, never
 * stored: a member who inherits a populated workspace never sees it, and one who
 * deletes everything gets it back, which is the correct behaviour for a checklist
 * that is really a description of the product.
 */
final class Onboarding
{
    /**
     * The steps, in the order the product wants them taken.
     *
     * @return array{complete: bool, steps: list<array{key: string, title: string, body: string, href: string, done: bool}>}
     */
    public static function checklist(): array
    {
        $buyers = Buyer::query()->count();
        $invoices = Invoice::query()->count();

        // "Is any invoice's evidence trail complete" — a count of invoices whose
        // present, required rows reach the required total, as one query rather than a
        // per-invoice loop on the dashboard's critical path.
        $required = EvidenceType::requiredValues();

        $completeInvoice = Invoice::query()
            ->withCount(['evidences as required_present' => fn ($q) => $q
                ->where('present', true)
                ->whereIn('type', $required),
            ])
            ->get()
            ->contains(fn (Invoice $invoice) => (int) $invoice->required_present >= count($required));

        $steps = [
            [
                'key' => 'buyer',
                'title' => 'Add a buyer',
                'body' => 'Their GSTIN and whether they are onboarded on TReDS decide which invoices can be discounted.',
                'href' => route('buyers.create'),
                'done' => $buyers > 0,
            ],
            [
                'key' => 'invoice',
                'title' => 'Raise an invoice',
                'body' => 'Enter the date and the amount — the '.config('paykaro.msme_due_days').'-day statutory window, the due date and the interest clock are derived from it.',
                'href' => route('invoices.create'),
                'done' => $invoices > 0,
            ],
            [
                'key' => 'evidence',
                'title' => 'Complete the evidence trail',
                'body' => 'Purchase order, delivery acknowledgement, GRN and a GST-valid copy. Dated proof of delivery is what makes a claim stand and an invoice discountable.',
                'href' => $invoices > 0 ? route('invoices.index') : route('invoices.create'),
                'done' => $completeInvoice,
            ],
            [
                'key' => 'onboarding',
                'title' => "Confirm each buyer's TReDS status",
                'body' => 'An unanswered status blocks discounting exactly as a missing GRN does — the readiness score counts it, so this is the other half of getting an invoice to '.config('paykaro.finance_ready_score').'.',
                'href' => route('buyers.index'),
                'done' => $buyers > 0 && ! Buyer::query()->where('treds_onboarded', TredsOnboarding::Unknown->value)->exists(),
            ],
        ];

        return [
            'complete' => ! in_array(false, array_column($steps, 'done'), true),
            'steps' => $steps,
        ];
    }
}
