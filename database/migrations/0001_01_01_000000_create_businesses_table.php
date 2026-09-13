<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The tenant root.
 *
 * Everything PayKaro tracks hangs off a business, and the identity columns
 * (GSTIN, PAN, Udyam) are the ones a claim or a TReDS transaction cites — which
 * is why they are editable only by an owner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('gstin')->nullable();
            $table->string('pan')->nullable();
            $table->string('udyam_no')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_acc_no')->nullable();
            $table->string('bank_ifsc')->nullable();
            $table->boolean('treds_registered')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
