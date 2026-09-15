<?php

namespace App\Models;

use App\Enums\BuyerType;
use App\Enums\InvoiceStatus;
use App\Enums\TredsOnboarding;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer who owes money, and the hinge between "invoice" and "financeable":
 * the buyer's TReDS onboarding decides whether an invoice can be discounted.
 */
class Buyer extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'gstin',
        'type',
        'treds_onboarded',
    ];

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'type' => BuyerType::class,
            'treds_onboarded' => TredsOnboarding::class,
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * What is still being chased: everything except settled and draft.
     */
    public function openInvoices(): HasMany
    {
        return $this->invoices()
            ->whereNotIn('status', [InvoiceStatus::Settled->value, InvoiceStatus::Draft->value]);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('name');
    }

    /**
     * Outstanding invoice value against this buyer.
     *
     * Reads the `open_invoices_sum_total_amount` aggregate when the caller
     * eager-loaded it (the buyers list does), so a page of N buyers costs one
     * query rather than 1 + N.
     */
    public function outstanding(): float
    {
        $aggregate = $this->getAttribute('open_invoices_sum_total_amount');

        if ($aggregate !== null) {
            return (float) $aggregate;
        }

        return (float) $this->openInvoices()->sum('total_amount');
    }
}
