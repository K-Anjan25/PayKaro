<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The tenant root: one PayKaro workspace per business.
 *
 * Every other table in the domain carries `business_id`, and users belong to
 * exactly one business, which is what the tenant scope keys off. Bank details
 * live here because they are what settled money is expected to land against —
 * only an owner may change them.
 */
class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'gstin',
        'pan',
        'udyam_no',
        'bank_name',
        'bank_acc_no',
        'bank_ifsc',
        'treds_registered',
    ];

    protected function casts(): array
    {
        return [
            'treds_registered' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function buyers(): HasMany
    {
        return $this->hasMany(Buyer::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /**
     * The account owner — the only member who may change the business's legal
     * and bank identity. A relation, not a helper, so views and eager loads
     * treat it like any other.
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', UserRole::Owner->value)->orderBy('id');
    }
}
