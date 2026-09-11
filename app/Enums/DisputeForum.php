<?php

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Where a delayed-payment claim is filed, and how long the clock gives.
 *
 * The MSEFC forum is time-bound and cheap, which is why it is the default;
 * arbitration is slower but final. Mediation has no statutory deadline, so the
 * packet leaves it blank rather than inventing one.
 */
enum DisputeForum: string
{
    case Msefc = 'msefc';
    case Mediation = 'mediation';
    case Arbitration = 'arbitration';

    public function label(): string
    {
        return match ($this) {
            self::Msefc => 'MSEFC (delayed payment)',
            self::Mediation => 'Mediation',
            self::Arbitration => 'Arbitration',
        };
    }

    /**
     * Short label for callouts.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Msefc => 'MSEFC',
            self::Mediation => 'Mediation',
            self::Arbitration => 'Arbitration',
        };
    }

    public function deadlineDays(): ?int
    {
        return match ($this) {
            self::Msefc => 45,
            self::Arbitration => 90,
            self::Mediation => null,
        };
    }

    /**
     * The filing deadline, measured from the invoice's due date.
     */
    public function deadlineFrom(CarbonInterface $dueDate): ?string
    {
        $days = $this->deadlineDays();

        return $days === null ? null : $dueDate->copy()->addDays($days)->toDateString();
    }
}
