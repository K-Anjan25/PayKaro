<?php

namespace App\Models;

use App\Enums\DisputeForum;
use App\Services\Receivables;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A delayed-payment claim filed against an invoice, and the deadline that comes
 * with it.
 *
 * The deadline is computed from the invoice's due date when the claim is opened
 * and then stored: a statutory clock that moved with the data would be worse
 * than useless in a forum.
 */
class Dispute extends Model
{
    use HasFactory;

    protected $fillable = [
        'forum',
        'stage',
        'filed_on',
        'deadline_on',
    ];

    protected function casts(): array
    {
        return [
            'invoice_id' => 'integer',
            'forum' => DisputeForum::class,
            'filed_on' => 'date',
            'deadline_on' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Days left before the filing deadline; negative once it has passed, null
     * for forums with no statutory deadline (mediation).
     */
    public function daysRemaining(): ?int
    {
        return app(Receivables::class)->daysUntil($this->deadline_on?->toDateString());
    }

    public function isPastDeadline(): bool
    {
        $days = $this->daysRemaining();

        return $days !== null && $days < 0;
    }

    public function deadlineLabel(): string
    {
        $days = $this->daysRemaining();

        return match (true) {
            $days === null => '—',
            $days < 0 => abs($days).' days past',
            default => 'in '.$days.' days',
        };
    }
}
