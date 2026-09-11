<?php

namespace App\Models;

use App\Enums\EvidenceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an invoice's evidence checklist.
 *
 * A row exists for every document type from the moment an invoice is raised, so
 * "missing" is a fact on record rather than the absence of a row — which is what
 * lets the claim packet say *which* document is missing.
 *
 * Reachable only through a tenant-scoped invoice, which is how it stays inside
 * its business without carrying `business_id` itself.
 */
class InvoiceEvidence extends Model
{
    use HasFactory;

    /**
     * Mandatory, not cosmetic: "evidence" is an *uncountable* noun, so Eloquent's
     * inflector resolves InvoiceEvidence to `invoice_evidence` — identical to its
     * singular form — and every query would target a table that does not exist.
     * The real name is inherited from the legacy schema (schema.sql) and the
     * migration of the same name.
     */
    protected $table = 'invoice_evidences';

    public $timestamps = false;

    protected $fillable = [
        'type',
        'present',
    ];

    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'type' => EvidenceType::class,
            'present' => 'boolean',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function label(): string
    {
        return $this->type->label();
    }
}
