<?php

namespace App\Enums;

/**
 * Where an invoice sits in the receivables pipeline.
 *
 * The happy path is raised -> accepted -> financed -> settled; `disputed`
 * branches off it, and `draft` is a not-yet-raised invoice that is excluded
 * from receivables totals on purpose.
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Raised = 'raised';
    case Accepted = 'accepted';
    case Financed = 'financed';
    case Settled = 'settled';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Raised => 'Raised',
            self::Accepted => 'Accepted',
            self::Financed => 'Financed',
            self::Settled => 'Settled',
            self::Disputed => 'Disputed',
        };
    }

    /**
     * Badge tone from the design system (see public/assets/app.css).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Raised => 'gold',
            self::Accepted => 'info',
            self::Financed, self::Settled => 'success',
            self::Disputed => 'danger',
            self::Draft => 'neutral',
        };
    }

    /**
     * The ordered pipeline a status timeline walks through.
     *
     * @return list<self>
     */
    public static function pipeline(): array
    {
        return [self::Raised, self::Accepted, self::Financed, self::Settled];
    }

    /**
     * Drafts are not yet receivables; settled invoices are.
     */
    public function countsTowardsReceivables(): bool
    {
        return $this !== self::Draft;
    }

    /**
     * The two states that end a receivable: paid, or under claim.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Settled, self::Disputed], true);
    }
}
