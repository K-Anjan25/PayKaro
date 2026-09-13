<?php

namespace App\Enums;

/**
 * What a team member may do inside their business.
 *
 * Viewers are read-only (the invoice, payment and claim mutations are refused),
 * accountants run the receivables book, and owners additionally control the
 * business identity — GSTIN, PAN and bank details — which is what settled money
 * lands against.
 */
enum UserRole: string
{
    case Owner = 'owner';
    case Accountant = 'accountant';
    case Viewer = 'viewer';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function canWrite(): bool
    {
        return $this !== self::Viewer;
    }

    public function canManageBusiness(): bool
    {
        return $this === self::Owner;
    }
}
