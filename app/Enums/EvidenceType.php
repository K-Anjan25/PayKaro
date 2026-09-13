<?php

namespace App\Enums;

/**
 * The evidence checklist attached to every invoice.
 *
 * These are the documents that turn "they took the goods in July" into a date a
 * forum accepts, so the same list drives the readiness score, the claim packet
 * and the checklist UI.
 */
enum EvidenceType: string
{
    case Po = 'po';
    case DeliveryAck = 'delivery_ack';
    case Grn = 'grn';
    case Contract = 'contract';
    case InvoiceCopy = 'invoice_copy';

    public function label(): string
    {
        return match ($this) {
            self::Po => 'Purchase order',
            self::DeliveryAck => 'Delivery acknowledgement',
            self::Grn => 'Goods receipt note',
            self::Contract => 'Contract',
            self::InvoiceCopy => 'GST-valid invoice copy',
        };
    }

    /**
     * Short label for dense tables and the claim packet.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Po => 'PO',
            self::DeliveryAck => 'Delivery ack',
            self::Grn => 'GRN',
            self::Contract => 'Contract',
            self::InvoiceCopy => 'GST copy',
        };
    }

    /**
     * The four documents a finance or dispute packet cannot be missing. The
     * contract is offered on every invoice but not required to score.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [self::Po, self::DeliveryAck, self::Grn, self::InvoiceCopy];
    }

    /**
     * @return list<string>
     */
    public static function requiredValues(): array
    {
        return array_map(fn (self $case) => $case->value, self::required());
    }

    /**
     * Every row the checklist seeds on a new invoice.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }

    public function isRequired(): bool
    {
        return in_array($this, self::required(), true);
    }
}
