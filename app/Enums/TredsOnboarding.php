<?php

namespace App\Enums;

/**
 * A buyer's TReDS onboarding status as far as we know it.
 *
 * `unknown` is deliberately distinct from `no`: an unanswered question is a
 * follow-up, a "no" is a blocked financing path.
 */
enum TredsOnboarding: string
{
    case Unknown = 'unknown';
    case Yes = 'yes';
    case No = 'no';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown',
            self::Yes => 'Yes',
            self::No => 'No',
        };
    }

    /**
     * Badge tone — an unanswered question reads neutral, a "no" reads blocked.
     */
    public function tone(): string
    {
        return match ($this) {
            self::Yes => 'success',
            self::No => 'danger',
            self::Unknown => 'neutral',
        };
    }

    public function isOnboarded(): bool
    {
        return $this === self::Yes;
    }
}
