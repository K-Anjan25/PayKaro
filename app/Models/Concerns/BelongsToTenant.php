<?php

namespace App\Models\Concerns;

use App\Models\Business;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as owned by exactly one Business (tenant).
 *
 * Three guarantees, all enforced here rather than at call sites:
 *  1. every query carries the tenant's `business_id` (TenantScope);
 *  2. every insert is stamped with the *resolved* tenant, so a payload can
 *     never write rows into another business by supplying its id;
 *  3. reaching any of it without a tenant throws, instead of reading the
 *     whole table.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (self $model) {
            $model->business_id = app(TenantContext::class)->requireId();
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
