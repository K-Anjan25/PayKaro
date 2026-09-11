<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Enums\EvidenceType;
use App\Models\Buyer;
use App\Models\Invoice;
use App\Services\Receivables;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 *
 * Amounts and the due date are produced the same way the app produces them —
 * through Receivables — so a factory row and a row raised from the form cannot
 * disagree about what "45 days" or "18% GST" means.
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $rules = app(Receivables::class);
        $invoiceDate = fake()->dateTimeBetween('-120 days', 'now')->format('Y-m-d');
        $base = round(fake()->randomFloat(2, 25000, 900000), 2);
        $tax = $rules->taxOn($base);

        return [
            'buyer_id' => Buyer::factory(),
            'number' => 'INV-'.fake()->unique()->numerify('2026-####'),
            'invoice_date' => $invoiceDate,
            'due_date' => $rules->dueDate($invoiceDate),
            'base_amount' => $base,
            'tax_amount' => $tax,
            'total_amount' => $rules->total($base, $tax),
            'status' => InvoiceStatus::Raised,
            'notes' => null,
        ];
    }

    public function forBuyer(Buyer $buyer): static
    {
        return $this->state(fn () => ['buyer_id' => $buyer->id]);
    }

    public function status(InvoiceStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    /**
     * An invoice that is already past due — the interesting case for interest.
     */
    public function overdue(int $days = 30): static
    {
        return $this->state(function () use ($days) {
            $rules = app(Receivables::class);
            $invoiceDate = now()->subDays($days + $rules->msmeDueDays)->toDateString();

            return [
                'invoice_date' => $invoiceDate,
                'due_date' => $rules->dueDate($invoiceDate),
            ];
        });
    }

    /**
     * Evidence checklist fully ticked.
     */
    public function withEvidence(array $types = ['po', 'delivery_ack', 'grn', 'invoice_copy', 'contract']): static
    {
        return $this->afterCreating(function (Invoice $invoice) use ($types) {
            foreach (EvidenceType::cases() as $type) {
                $invoice->evidences()->create([
                    'type' => $type,
                    'present' => in_array($type->value, $types, true),
                ]);
            }
        });
    }
}
