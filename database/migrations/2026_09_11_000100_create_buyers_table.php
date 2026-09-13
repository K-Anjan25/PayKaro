<?php

use App\Enums\BuyerType;
use App\Enums\TredsOnboarding;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers, and the TReDS onboarding flag that decides whether their invoices
 * can be discounted at all.
 *
 * `type` exists because the 2026 amendment mandates exchange use for CPSE
 * buyers: for a supplier, "this buyer is a CPSE and is not onboarded" is a
 * follow-up worth making, not trivia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buyers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('gstin')->nullable();
            $table->string('type', 16)->default(BuyerType::Private->value);
            $table->string('treds_onboarded', 16)->default(TredsOnboarding::Unknown->value);
            $table->timestamps();

            $table->index(['business_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyers');
    }
};
