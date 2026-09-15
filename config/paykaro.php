<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Identity
    |--------------------------------------------------------------------------
    |
    | The marketing shell and the workspace share these strings. APP_NAME
    | (config/app.php) stays the source of truth for the app name; these two only
    | appear in the header, footer, <title> and auth pages.
    |
    | Two keys, not one, because they do two different jobs — and holding both in
    | one key is what let the product end up with five wordings of the same
    | promise (BRAND_PLAN §1.1):
    |
    |   headline    the brand line a visitor reads. It has to be *identical* on
    |               the landing page, in the page <title>, on the auth pages and
    |               in the share card, so it lives here and nowhere else.
    |   descriptor  the one-line explanation that sits under the wordmark. It is
    |               not a tagline, and it reads wrong when it is used as one.
    |
    | This key was `paykaro.tagline` until the two were split; a deployment that
    | sets PAYKARO_TAGLINE must be renamed rather than keep the old value.
    */

    'headline' => env('PAYKARO_HEADLINE', 'Make every invoice count'),

    'descriptor' => env('PAYKARO_DESCRIPTOR', 'MSME invoice & receivables tracker'),

    /*
    |--------------------------------------------------------------------------
    | Demo workspace
    |
    | The seeded workspace is labelled "Demo workspace" in the utility bar and
    | the demo logins are printed by the seeder. Turn this off when the app is
    | pointed at a real book of business.
    |
    */

    'demo' => (bool) env('PAYKARO_DEMO', true),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | Amounts are rendered with Indian digit grouping (lakh/crore), which is
    | what App\Support\Money does — this symbol is what it prefixes.
    |
    */

    'currency' => [
        'symbol' => '₹',
        'code' => 'INR',
    ],

    /*
    |--------------------------------------------------------------------------
    | MSMED Act rules
    |--------------------------------------------------------------------------
    |
    | Every number a claim or a discounting decision rests on lives here:
    |
    |  - msme_due_days ......... payments to MSMEs fall due within 45 days
    |                            unless a written agreement says otherwise.
    |  - default_tax_rate ........ GST applied when an invoice is raised
    |                            without an explicit tax figure.
    |  - bank_rate x multiplier .. statutory interest on delayed payments:
    |                            compound interest with monthly rests at 3x the
    |                            bank rate (Section 16, MSMED Act 2006). This is
    |                            the Bank Rate the RBI notifies, not the repo rate
    |                            — the Bank Rate has been 5.50% since December 2025.
    |  - bank_rate_history ...... optional: the notifications, so a period that saw
    |                            a rate change is charged each rate for its own part.
    |  - finance_ready_score ... the readiness score at which an invoice is
    |                            treated as financeable on the TReDS queue.
    |
    | Change them here — the domain services, the ageing chart and the claim
    | packet all read this file rather than hard-coding anything.
    |
    */

    'msme_due_days' => (int) env('PAYKARO_MSME_DUE_DAYS', 45),

    'default_tax_rate' => (float) env('PAYKARO_DEFAULT_TAX_RATE', 18),

    'bank_rate' => (float) env('PAYKARO_BANK_RATE', 5.5),

    /*
     * Bank-rate notifications, oldest first, as `['from' => 'Y-m-d', 'rate' => 5.5]`.
     * Empty by default: these are the operator's records, and a wrong date would
     * quietly mis-price a claim. With one entry nothing changes; with several, a
     * rest uses the rate in force on its own date.
     */
    'bank_rate_history' => [],

    'interest_multiplier' => (int) env('PAYKARO_INTEREST_MULTIPLIER', 3),

    'finance_ready_score' => (int) env('PAYKARO_FINANCE_READY_SCORE', 85),

    /*
    |--------------------------------------------------------------------------
    | Readiness scoring weights
    |--------------------------------------------------------------------------
    |
    | Evidence completeness dominates the score because a missing document is
    | what actually stops a claim or a discount. Buyer onboarding and the
    | overdue-ness of the invoice are the tie-breakers.
    |
    */

    'readiness_weights' => [
        'evidence' => 70,
        // A buyer confirmed onboard unlocks discounting; a buyer confirmed
        // *not* onboard is still a shorter gap than an unanswered question.
        'buyer_onboarded' => 20,
        'buyer_not_onboarded' => 5,
        'overdue' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts
    |--------------------------------------------------------------------------
    |
    | "Needs attention" on the dashboard reads the tenant's unread alerts.
    |
    */

    'alert_limit' => (int) env('PAYKARO_ALERT_LIMIT', 6),

];
