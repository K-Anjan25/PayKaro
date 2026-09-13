<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An invoice discounted on TReDS: who financed it, at what discount, and how
 * much actually landed.
 */
class Financing extends Model
{
    use HasFactory;

    protected $fillable = [
        'financier',
        'discount_rate',
        'amount_disbursed',
        'disbursed_on',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'discount_rate' => 'decimal:2',
            'amount_disbursed' => 'decimal:2',
            'disbursed_on' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function amountLabel(): string
    {
        return Money::inr($this->amount_disbursed);
    }
}
