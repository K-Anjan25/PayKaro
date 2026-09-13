<?php

namespace App\Enums;

/**
 * Buyer class, which drives TReDS expectations: CPSE buyers are mandated to
 * onboard and transact on the exchange, PSU buyers usually have, private
 * buyers often have not.
 */
enum BuyerType: string
{
    case Cpse = 'cpse';
    case Psu = 'psu';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Cpse => 'CPSE',
            self::Psu => 'PSU',
            self::Private => 'Private',
        };
    }

    /**
     * Whether this class of buyer is expected to be reachable on TReDS.
     */
    public function isTredsMandated(): bool
    {
        return $this === self::Cpse;
    }
}
