<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a buyer is emailed.
 *
 * Nullable on purpose, and the app says so rather than failing: most MSME
 * customer records arrive with a name and a GSTIN and nothing else, and an
 * invoice can be tracked (and chased by phone, which is what actually happens)
 * without an address on file. Sending is the one action that needs it, so sending
 * is the one action that asks for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            $table->string('email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('buyers', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
