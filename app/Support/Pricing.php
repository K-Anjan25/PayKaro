<?php

namespace App\Support;

/**
 * Plan tiers shown on /pricing and referenced by the workspace's "Upgrade to
 * Pro" card.
 *
 * Kept beside News rather than in config: it is marketing copy that happens to
 * describe the product, not a rule the domain computes with.
 */
final class Pricing
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function plans(): array
    {
        return [
            [
                'name' => 'Starter',
                'price' => 'Free',
                'per' => 'forever',
                'blurb' => 'For a single MSME getting its receivables in order.',
                'cta' => route('register'),
                'cta_label' => 'Start free',
                'cta_class' => 'pbtn-outline',
                'highlight' => false,
                'features' => [
                    'Up to 25 invoices',
                    '1 user (owner)',
                    'Invoice pipeline + interest',
                    'Evidence checklist',
                    'Demo data included',
                ],
            ],
            [
                'name' => 'Pro',
                'price' => '₹1,499',
                'per' => '/month · per business',
                'blurb' => 'For growing suppliers who finance and claim often.',
                'cta' => route('register'),
                'cta_label' => 'Try Pro free',
                'cta_class' => 'pbtn-primary',
                'highlight' => true,
                'features' => [
                    'Unlimited invoices',
                    '3 users (owner + accountant)',
                    'TReDS / finance queue',
                    'MSEFC claim packet builder',
                    'Advanced reports & export',
                    'Priority support',
                ],
            ],
            [
                'name' => 'Enterprise',
                'price' => 'Custom',
                'per' => 'let’s talk',
                'blurb' => 'For CA firms, lenders and multi-entity groups.',
                'cta' => route('login'),
                'cta_label' => 'Book a demo',
                'cta_class' => 'pbtn-blue',
                'highlight' => false,
                'features' => [
                    'Everything in Pro',
                    'Multi-entity / portfolio view',
                    'API & data exports',
                    'Onboarding & training',
                    'Dedicated success manager',
                ],
            ],
        ];
    }
}
