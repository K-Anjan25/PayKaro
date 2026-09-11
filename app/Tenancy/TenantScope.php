<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts a model's queries to the current business.
 *
 * This is what makes isolation a property of the data layer instead of a
 * convention each controller has to remember: `Invoice::all()` is already
 * `WHERE invoices.business_id = <tenant>`, and `Invoice::find($foreignId)`
 * returns null — so a shared or guessed id renders "not found" rather than
 * someone else's receivables.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where(
            $model->qualifyColumn('business_id'),
            app(TenantContext::class)->requireId()
        );
    }
}
