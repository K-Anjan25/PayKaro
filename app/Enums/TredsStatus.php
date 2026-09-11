<?php

namespace App\Enums;

/**
 * Whether an invoice can be discounted on TReDS right now, and if not, why.
 *
 * Computed by App\Services\Receivables from the invoice status, its evidence
 * trail and the buyer's onboarding — never stored, so it cannot go stale.
 */
enum TredsStatus: string
{
    case Na = 'na';
    case Ready = 'ready';
    case PendingBuyerOnboard = 'pending_buyer_onboard';
    case Financed = 'financed';
    case Ineligible = 'ineligible';

    public function label(): string
    {
        return match ($this) {
            self::Na => '—',
            self::Ready => 'TReDS ready',
            self::PendingBuyerOnboard => 'Buyer not onboard',
            self::Financed => 'Financed',
            self::Ineligible => 'Ineligible',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Ready, self::Financed => 'success',
            self::PendingBuyerOnboard => 'warning',
            self::Ineligible => 'danger',
            self::Na => 'neutral',
        };
    }

    /**
     * What the finance queue shows: real candidates and the ones blocked by
     * buyer onboarding (the gap a supplier can actually close).
     */
    public function isInFinanceQueue(): bool
    {
        return in_array($this, [self::Ready, self::PendingBuyerOnboard], true);
    }

    public function isFinanceReady(): bool
    {
        return $this === self::Ready;
    }
}
