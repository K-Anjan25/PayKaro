<?php

use App\Enums\InvoiceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoices: the stored facts only.
 *
 * Note what is *not* here — no balance, no days-overdue, no interest, no
 * readiness, no `treds_status` column. All of that is derived from these columns
 * plus config (App\Services\Receivables), because a stored copy of a computed
 * number is a number that quietly goes wrong overnight.
 *
 * Amounts are DECIMAL rather than the legacy REAL: this is money, and float
 * rounding has no business deciding whether a claim is settled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained()->cascadeOnDelete();
            $table->string('number', 60);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('base_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->string('status', 16)->default(InvoiceStatus::Raised->value);
            $table->date('approval_date')->nullable();
            $table->date('paid_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // A number is unique within a workspace, not across tenants: two
            // suppliers both raising INV-2026-001 is normal.
            $table->unique(['business_id', 'number']);

            $table->index(['business_id', 'status']);
            $table->index(['business_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
