<?php

namespace App\Models;

use App\Enums\AlertType;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An attention item for one business — the dashboard's "Needs attention" list.
 *
 * Alerts are generated from the invoice (a buyer not on TReDS, a claim just
 * filed) and dismissed by the user, never deleted: `read_at` keeps the history.
 */
class Alert extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'invoice_id',
        'type',
        'message',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'invoice_id' => 'integer',
            'type' => AlertType::class,
            'read_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function tone(): string
    {
        return $this->type?->tone() ?? '';
    }
}
