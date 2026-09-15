<?php

namespace App\Support;

use App\Enums\EvidenceType;

/**
 * The only figures the marketing pages are allowed to publish (BRAND_PLAN §4).
 *
 * The landing page and the sign-in shell were quoting traction: **₹4.2Cr+
 * "Receivables tracked"**, *"Across active tenants in the last 30 days"*, **₹240Cr+
 * "Invoices cleared"**, **99.8% "Reconciliation rate"**, **<48 Hrs "Disbursal
 * speed"**. None of it was computed by anything — they were literals in the Blade
 * files — and the only data the product has ever held is its own seeded demo book.
 * `app/Support/Legal.php` is scrupulous about admitting what the product does *not*
 * do; the marketing pages were doing the opposite.
 *
 * So the replacements are the numbers the product actually enforces, read from the
 * same config and enums the domain computes with — change `PAYKARO_MSME_DUE_DAYS`
 * and the landing page changes with it. Two rules follow, and they are the whole
 * point of this class:
 *
 *   1. A marketing page may only state a figure that comes from here.
 *   2. A figure here must be checkable in the code — no traction, no percentages
 *      of anything nobody measures.
 *
 * `tests/Feature/AuthenticityTest.php` holds both.
 */
final class Proof
{
    /**
     * The claims, in the order they read best.
     *
     * @return list<array{value: string, label: string, note: string}>
     */
    public static function claims(): array
    {
        $dueDays = (int) config('paykaro.msme_due_days');
        $multiplier = (int) config('paykaro.interest_multiplier');
        $bankRate = (float) config('paykaro.bank_rate');
        $required = count(EvidenceType::required());
        $threshold = (int) config('paykaro.finance_ready_score');

        return [
            [
                'value' => $dueDays.' days',
                'label' => 'MSME due window',
                'note' => 'The statutory window we track against, from configuration — surfaced as a live deadline on every invoice.',
            ],
            [
                'value' => $multiplier.'×',
                'label' => 'Bank-rate interest',
                'note' => 'Interest on an overdue invoice compounds at monthly rests at '.$multiplier.'× the prevailing '.$bankRate.'% bank rate, under Section 16 of the MSMED Act, 2006.',
            ],
            [
                'value' => (string) $required,
                'label' => 'Documents per invoice',
                'note' => 'Every invoice carries the same checklist — the one that decides the readiness score and fills the claim packet.',
            ],
            [
                'value' => (string) $threshold,
                'label' => 'Readiness score',
                'note' => 'The score at which an invoice leaves the finance queue as discountable. Evidence carries most of it.',
            ],
        ];
    }

    /**
     * The short forms, for the sign-in shell's three-up strip.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function highlights(): array
    {
        return array_map(
            fn (array $claim) => ['value' => $claim['value'], 'label' => $claim['label']],
            array_slice(self::claims(), 0, 3),
        );
    }

    /**
     * What the product does *not* claim, stated where a visitor can read it.
     *
     * The companion to Legal's disclosure style: a page that admits the boundaries
     * of its own claims is the page that gets believed.
     *
     * @return list<string>
     */
    public static function limits(): array
    {
        return [
            'No published customer count or tracked-value figure — the demo workspace is seeded, and we do not quote it as traction.',
            'No percentage claims about reconciliation, disbursal speed or accuracy: nothing in the product measures them.',
            'No bank, NBFC or TReDS partnership implied beyond what TReDS registration actually means.',
        ];
    }
}
