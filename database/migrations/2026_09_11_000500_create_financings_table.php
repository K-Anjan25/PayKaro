<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice discounting on TReDS: who financed it, at what discount, what landed.
 *
 * Table renamed from the legacy `financing` to `financings` so it follows the
 * convention the rest of the schema (and `hasMany(Financing::class)`) expects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('financier')->nullable();
            $table->decimal('discount_rate', 6, 2)->nullable();
            $table->decimal('amount_disbursed', 14, 2)->nullable();
            $table->date('disbursed_on')->nullable();
            $table->string('status', 24)->default('disbursed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financings');
    }
};
