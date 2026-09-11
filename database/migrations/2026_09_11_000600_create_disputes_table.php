<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Delayed-payment claims.
 *
 * `deadline_on` is stored, not derived: once a claim is filed the statutory
 * clock belongs to the filing, so changing an invoice's due date afterwards
 * must not silently move a deadline that has already been quoted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('forum', 24);
            $table->string('stage', 24)->nullable();
            $table->date('filed_on')->nullable();
            $table->date('deadline_on')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};
