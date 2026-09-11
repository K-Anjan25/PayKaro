<?php

namespace App\Models;

use App\Enums\AgeingBucket;
use App\Enums\EvidenceType;
use App\Enums\HealthStatus;
use App\Enums\InvoiceStatus;
use App\Enums\TredsOnboarding;
use App\Enums\TredsStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Services\Receivables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One invoice — the asset the whole product revolves around.
 *
 * Stored columns are only the facts (dates, amounts, status, buyer). Everything
 * a claim or a discount actually rests on — days overdue, accrued interest,
 * ageing bucket, readiness score, TReDS eligibility, balance — is *derived* by
 * App\Services\Receivables from those facts plus config, so it can never go
 * stale or disagree with the statute after a rate change.
 *
 * Tenant isolation is not this class's job: it comes from `BelongsToTenant`, so
 * `Invoice::find($otherBusinessId)` is simply not found.
 *
 * The derived values are ordinary methods rather than magic accessors: nothing
 * collides with a column name, and `Invoice::find(1)->balance()` reads the same
 * in a controller, a Blade table and a test.
 */
class Invoice extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'buyer_id',
        'number',
        'invoice_date',
        'due_date',
        'base_amount',
        'tax_amount',
        'total_amount',
        'status',
        'approval_date',
        'paid_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
            'buyer_id' => 'integer',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'approval_date' => 'date',
            'paid_date' => 'date',
            'base_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => InvoiceStatus::class,
        ];
    }

    /* ------------------------------------------------------------------ *
     * Relations
     * ------------------------------------------------------------------ */

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(InvoiceEvidence::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function financings(): HasMany
    {
        return $this->hasMany(Financing::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    /**
     * Evidence rows in checklist order: the required documents first, then
     * anything optional.
     */
    public function checklist(): HasMany
    {
        $required = EvidenceType::requiredValues();

        $placeholders = implode(',', array_fill(0, count($required), '?'));

        return $this->evidences()->orderByRaw(
            "CASE WHEN type IN ({$placeholders}) THEN 0 ELSE 1 END, id",
            $required,
        );
    }

    /* ------------------------------------------------------------------ *
     * Scopes
     * ------------------------------------------------------------------ */

    public function scopeOfStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || $status === 'all') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeForBuyer(Builder $query, int|string|null $buyerId): Builder
    {
        return blank($buyerId) ? $query : $query->where('buyer_id', (int) $buyerId);
    }

    /**
     * Anything still owed: not settled, not a draft.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            InvoiceStatus::Settled->value,
            InvoiceStatus::Draft->value,
        ]);
    }

    /**
     * Free-text search from the workspace header: invoice number or buyer name.
     *
     * Wildcards in the term are dropped rather than escaped, so "100%" searches
     * for a literal percent on every driver instead of depending on LIKE's
     * escape rules (SQLite has none, MySQL's is a backslash).
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim(str_replace(['%', '_', '\\'], ' ', (string) $term));

        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(
            fn (Builder $inner) => $inner
                ->where('number', 'like', $like)
                ->orWhereHas('buyer', fn (Builder $buyers) => $buyers->where('name', 'like', $like))
        );
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('invoice_date')->orderByDesc('id');
    }

    /**
     * Load everything the derived values below need, in one query set.
     *
     * Without this a 15-row invoice table would fire a payments sum and an
     * evidence count per row — the N+1 the legacy loop had by construction.
     */
    public function scopeWithMetrics(Builder $query): Builder
    {
        return $query
            ->with(['buyer', 'evidences'])
            ->withSum('payments', 'amount')
            ->withCount([
                'evidences as required_evidence_present' => fn (Builder $q) => $q
                    ->whereIn('type', EvidenceType::requiredValues())
                    ->where('present', true),
            ]);
    }

    /* ------------------------------------------------------------------ *
     * Derived values (delegated to the domain service)
     * ------------------------------------------------------------------ */

    public function dueDateValue(): ?string
    {
        return $this->due_date?->toDateString();
    }

    public function overdueDays(): int
    {
        return $this->receivables()->overdueDays($this->dueDateValue(), $this->status);
    }

    public function interest(): float
    {
        return $this->receivables()->interest((float) $this->total_amount, $this->overdueDays());
    }

    public function ageing(): AgeingBucket
    {
        return $this->receivables()->ageing($this->overdueDays());
    }

    public function balance(): float
    {
        return $this->receivables()->balance(
            (float) $this->total_amount,
            $this->paidTotal(),
            $this->status,
        );
    }

    public function paidTotal(): float
    {
        $aggregate = $this->getAttribute('payments_sum_amount');

        if ($aggregate !== null) {
            return (float) $aggregate;
        }

        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->sum('amount');
        }

        return (float) $this->payments()->sum('amount');
    }

    /**
     * How many of the required documents are ticked off.
     */
    public function presentRequiredEvidence(): int
    {
        $aggregate = $this->getAttribute('required_evidence_present');

        if ($aggregate !== null) {
            return (int) $aggregate;
        }

        if ($this->relationLoaded('evidences')) {
            return $this->evidences
                ->filter(fn (InvoiceEvidence $evidence) => $evidence->present && $evidence->type->isRequired())
                ->count();
        }

        return $this->evidences()
            ->whereIn('type', EvidenceType::requiredValues())
            ->where('present', true)
            ->count();
    }

    public function buyerOnboarding(): TredsOnboarding
    {
        return $this->buyer?->treds_onboarded ?? TredsOnboarding::Unknown;
    }

    public function requiredEvidenceCount(): int
    {
        return count(EvidenceType::required());
    }

    public function readiness(): int
    {
        return $this->receivables()->readiness(
            $this->presentRequiredEvidence(),
            $this->requiredEvidenceCount(),
            $this->buyerOnboarding(),
            $this->overdueDays(),
        );
    }

    public function treds(): TredsStatus
    {
        return $this->receivables()->tredsStatus($this->status, $this->buyerOnboarding());
    }

    public function health(): HealthStatus
    {
        return HealthStatus::for($this->status, $this->overdueDays());
    }

    /**
     * Ready enough to put in front of a financier: eligible on the exchange and
     * past the workspace's readiness threshold.
     */
    public function isFinanceReady(): bool
    {
        return $this->treds()->isFinanceReady()
            && $this->receivables()->isFinanceReady($this->readiness());
    }

    public function canBeFinanced(): bool
    {
        return ! in_array($this->status, [
            InvoiceStatus::Financed,
            InvoiceStatus::Settled,
            InvoiceStatus::Disputed,
        ], true);
    }

    /**
     * Evidence rows in checklist order, from an already-loaded collection:
     * required documents first, optional ones after.
     *
     * @param  \Illuminate\Support\Collection<int, InvoiceEvidence>  $evidences
     * @return \Illuminate\Support\Collection<int, InvoiceEvidence>
     */
    public static function sortChecklist(\Illuminate\Support\Collection $evidences): \Illuminate\Support\Collection
    {
        $order = array_flip(EvidenceType::requiredValues());

        return $evidences->sortBy(
            fn (InvoiceEvidence $evidence) => $order[$evidence->type->value] ?? count($order)
        )->values();
    }

    public function canBeDisputed(): bool
    {
        return ! in_array($this->status, [
            InvoiceStatus::Disputed,
            InvoiceStatus::Settled,
        ], true);
    }

    protected function receivables(): Receivables
    {
        return app(Receivables::class);
    }
}
