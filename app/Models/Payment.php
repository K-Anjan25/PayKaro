<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money received against an invoice.
 *
 * Payments are recorded, never edited: the ledger a claim stands on has to be
 * append-only, and the workflow caps an entry at the outstanding balance so a
 * fat-fingered amount cannot turn an invoice into a credit.
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'amount',
        'paid_on',
        'method',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function amountLabel(): string
    {
        return Money::inr($this->amount);
    }
}
