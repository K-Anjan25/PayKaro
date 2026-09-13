<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * `php artisan migrate --seed` gives a working demo: two businesses, each with
 * an owner, buyers and a live book of invoices.
 *
 * Model events stay enabled on purpose — the seed runs through the same
 * InvoiceWorkflow the app uses, so a fresh install exercises tenant stamping,
 * due-date derivation and the evidence checklist rather than bypassing them.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DemoWorkspaceSeeder::class);
    }
}
