<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-invoice evidence checklist.
 *
 * A row exists for every document type the moment an invoice is raised, so a
 * missing document is a recorded `present = false` rather than an absent row.
 * That distinction is the whole claim packet: "GRN missing" is a fact to show a
 * forum, not a query that returned nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->boolean('present')->default(false);

            $table->unique(['invoice_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_evidences');
    }
};
