<?php

namespace App\Enums;

/**
 * The one-glance verdict in the invoice table: paid, due soon, or overdue by
 * how much.
 *
 * Derived, never stored — a stored "health" column would be wrong the moment
 * the date rolled over.
 */
enum HealthStatus: string
{
    case Paid = 'paid';
    case DueSoon = 'due_soon';
    case Overdue1To30 = 'overdue_1_30';
    case Overdue31To60 = 'overdue_31_60';
    case Overdue61To90 = 'overdue_61_90';
    case Overdue90Plus = 'overdue_90_plus';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::DueSoon => 'Due Soon',
            self::Overdue1To30 => 'Overdue (1-30)',
            self::Overdue31To60 => 'Overdue (31-60)',
            self::Overdue61To90 => 'Overdue (61-90)',
            self::Overdue90Plus => 'Overdue (90+)',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Paid => 'success',
            self::DueSoon => 'info',
            default => 'danger',
        };
    }

    public static function for(InvoiceStatus $status, int $overdueDays): self
    {
        if ($status === InvoiceStatus::Settled) {
            return self::Paid;
        }

        if ($overdueDays <= 0) {
            return self::DueSoon;
        }

        return match (true) {
            $overdueDays <= 30 => self::Overdue1To30,
            $overdueDays <= 60 => self::Overdue31To60,
            $overdueDays <= 90 => self::Overdue61To90,
            default => self::Overdue90Plus,
        };
    }
}
