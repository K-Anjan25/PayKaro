<?php

namespace Tests\Feature;

use App\Enums\EvidenceType;
use App\Enums\InvoiceStatus;
use App\Enums\TredsOnboarding;
use App\Enums\UserRole;
use App\Models\Alert;
use App\Models\Business;
use App\Models\Buyer;
use App\Models\Dispute;
use App\Models\Financing;
use App\Models\Invoice;
use App\Models\InvoiceEvidence;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * Every model must resolve to a table that actually exists.
 *
 * This guards a real bug: "evidence" is an *uncountable* noun, so Eloquent
 * inferred `invoice_evidence` for InvoiceEvidence while the migration creates
 * `invoice_evidences`. Nothing complains at boot — the app renders, signs you
 * in, and then 500s with "no such table" the first time the dashboard counts a
 * checklist. So assert the inferred/declared name of every model against the
 * migrated schema, then run the one query that touches every table at once.
 */
final class SchemaTableNamesTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    /**
     * @return array<string, array{0: class-string}>
     */
    public static function modelProvider(): array
    {
        return [
            'business' => [Business::class],
            'user' => [User::class],
            'buyer' => [Buyer::class],
            'invoice' => [Invoice::class],
            'invoice evidence' => [InvoiceEvidence::class],
            'payment' => [Payment::class],
            'financing' => [Financing::class],
            'dispute' => [Dispute::class],
            'alert' => [Alert::class],
        ];
    }

    #[DataProvider('modelProvider')]
    public function test_every_model_resolves_to_a_migrated_table(string $model): void
    {
        $table = (new $model)->getTable();

        $this->assertTrue(
            Schema::hasTable($table),
            "{$model} resolves to table [{$table}], which no migration creates — declare \$table on the model or fix the migration."
        );
    }

    public function test_evidence_checklist_is_reachable_through_the_invoice(): void
    {
        $owner = $this->workspace(UserRole::Owner);
        $buyer = $this->buyerAs($owner, ['treds_onboarded' => TredsOnboarding::Yes]);

        $invoice = $this->invoice($owner, $buyer, ['status' => InvoiceStatus::Accepted])
            ->tap(fn (Invoice $i) => collect(EvidenceType::cases())->each(
                fn (EvidenceType $type) => $i->evidences()->create(['type' => $type, 'present' => $type->isRequired()])
            ));

        $this->assertSame('invoice_evidences', (new InvoiceEvidence)->getTable());
        $this->assertCount(5, $invoice->evidences);
        $this->assertSame(4, $invoice->refresh()->presentRequiredEvidence());
        $this->assertSame(4, Invoice::query()->withMetrics()->find($invoice->id)->getAttribute('required_evidence_present'));
    }

    public function test_the_dashboard_survives_a_workspace_with_one_paid_invoice(): void
    {
        $owner = $this->workspace(UserRole::Owner);
        $buyer = $this->buyerAs($owner, ['treds_onboarded' => TredsOnboarding::Yes]);

        $this->invoice($owner, $buyer, [
            'invoice_date' => now()->subDays(60)->toDateString(),
            'base_amount' => 100000,
            'tax_amount' => 18000,
            'status' => InvoiceStatus::Accepted,
        ])->tap(function (Invoice $invoice) {
            foreach (EvidenceType::required() as $type) {
                $invoice->evidences()->create(['type' => $type, 'present' => true]);
            }

            $invoice->payments()->create([
                'amount' => 40000,
                'paid_on' => now()->toDateString(),
                'method' => 'NEFT',
            ]);
        });

        $summary = app(\App\Services\Dashboard::class)->overview();

        $this->assertSame(78000.0, round($summary->total, 2));

        $this->get('/dashboard')->assertOk();
    }
}
