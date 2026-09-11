<?php

namespace App\Enums;

/**
 * The kinds of attention item the workspace raises for a business.
 */
enum AlertType: string
{
    case Treds = 'treds';
    case Dispute = 'dispute';

    public function label(): string
    {
        return match ($this) {
            self::Treds => 'TReDS',
            self::Dispute => 'Claim',
        };
    }

    /**
     * Visual weight in the "Needs attention" list.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Dispute => 'danger',
            self::Treds => '',
        };
    }
}
