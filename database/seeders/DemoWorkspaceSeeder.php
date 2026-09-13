<?php

namespace Database\Seeders;

use App\Enums\BuyerType;
use App\Enums\DisputeForum;
use App\Enums\EvidenceType;
use App\Enums\InvoiceStatus;
use App\Enums\TredsOnboarding;
use App\Enums\UserRole;
use App\Models\Business;
use App\Models\Buyer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\InvoiceWorkflow;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Two demo businesses, so multi-tenant isolation is *demonstrable* rather than
 * merely asserted: each account sees a different book, and neither can reach the
 * other's rows by editing a URL.
 *
 * Invoices are raised through InvoiceWorkflow — the same path a real user takes —
 * so the seeded due dates, GST, evidence checklist and "buyer not on TReDS"
 * alerts are derived by the application rather than hard-coded here. Invoice
 * dates are offsets from today, which keeps ageing, interest and the finance
 * queue meaningful whenever the demo happens to be seeded.
 */
class DemoWorkspaceSeeder extends Seeder
{
    public function __construct(
        private readonly InvoiceWorkflow $workflow,
        private readonly TenantContext $tenant,
    ) {}

    public function run(): void
    {
        if (($existing = User::query()->count()) > 0) {
            // The usual reason a first run looks "seeded but empty": somebody
            // signed up on /signup before this ran, so the guard below fired.
            $this->command?->warn("Database already has {$existing} user(s) — skipping the demo seed.");
            $this->command?->line('  To get the two demo tenants instead: php artisan migrate:fresh --seed');

            return;
        }

        $this->workspace(
            business: [
                'name' => 'Shree Precision Components',
                'gstin' => '36AAACS1234F1Z5',
                'pan' => 'AAACS1234F',
                'udyam_no' => 'UDYAM-TS-12-3456789',
                'bank_name' => 'HDFC Bank',
                'bank_acc_no' => '50100234567890',
                'bank_ifsc' => 'HDFC0001234',
                'treds_registered' => true,
            ],
            owner: ['name' => 'Sunita Rao', 'email' => 'sunita@shreeprecision.in'],
            buyers: [
                ['Bharat Heavy Electricals Ltd', '36AABCB1234C1Z3', BuyerType::Cpse, TredsOnboarding::Yes],
                ['Telangana State Powergen', '36AAACT5678D1Z8', BuyerType::Psu, TredsOnboarding::Yes],
                ['Orbit Auto Components Pvt Ltd', '36AAGCO9876E1Z2', BuyerType::Private, TredsOnboarding::No],
                ['Hydrofit Engineering LLP', '36AAJFH2468F1Z9', BuyerType::Private, TredsOnboarding::Unknown],
            ],
            invoices: [
                [0, 'INV-2026-001', -92, 485000, InvoiceStatus::Settled, ['po', 'delivery_ack', 'grn', 'invoice_copy', 'contract']],
                [0, 'INV-2026-002', -48, 210000, InvoiceStatus::Accepted, ['po', 'delivery_ack', 'grn', 'invoice_copy']],
                [0, 'INV-2026-003', -70, 360000, InvoiceStatus::Accepted, ['po', 'delivery_ack', 'grn', 'invoice_copy', 'contract']],
                [0, 'INV-2026-004', -26, 92000, InvoiceStatus::Raised, ['po', 'delivery_ack', 'grn', 'invoice_copy']],
                [1, 'INV-2026-005', -80, 640000, InvoiceStatus::Accepted, ['po', 'delivery_ack', 'grn', 'invoice_copy']],
                [1, 'INV-2026-006', -33, 150000, InvoiceStatus::Raised, ['po', 'delivery_ack', 'grn']],
                [1, 'INV-2026-007', -110, 820000, InvoiceStatus::Disputed, ['po', 'delivery_ack', 'grn', 'contract']],
                [2, 'INV-2026-008', -15, 76000, InvoiceStatus::Raised, ['po', 'delivery_ack', 'grn', 'invoice_copy']],
                [2, 'INV-2026-009', -60, 200000, InvoiceStatus::Accepted, ['po', 'delivery_ack', 'grn']],
                [2, 'INV-2026-010', -5, 45000, InvoiceStatus::Raised, ['po', 'grn']],
                [3, 'INV-2026-011', -42, 130000, InvoiceStatus::Raised, ['po', 'delivery_ack', 'grn', 'invoice_copy']],
                [3, 'INV-2026-012', -100, 300000, InvoiceStatus::Accepted, ['po', 'delivery_ack', 'grn', 'contract']],
                [0, 'INV-2026-013', -18, 88000, InvoiceStatus::Financed, ['po', 'delivery_ack', 'grn', 'invoice_copy', 'contract']],
                [1, 'INV-2026-014', -12, 64000, InvoiceStatus::Accepted, ['po', 'delivery_ack', 'grn', 'invoice_copy']],
                [2, 'INV-2026-015', -8, 32000, InvoiceStatus::Raised, ['po', 'invoice_copy']],
            ],
        );

        $this->workspace(
            business: [
                'name' => 'MetRow Ceramics',
                'gstin' => '29AAACM5678G1Z4',
                'pan' => 'AAACM5678G',
                'udyam_no' => 'UDYAM-KA-11-7654321',
                'bank_name' => 'SBI',
                'bank_acc_no' => '30123456789',
                'bank_ifsc' => 'SBIN0004567',
                'treds_registered' => false,
            ],
            owner: ['name' => 'Farhan Ali', 'email' => 'farhan@metrowceramics.in'],
            buyers: [
                ['Delhi Metro Rail Corp', '07AADCM2222H1Z1', BuyerType::Psu, TredsOnboarding::Yes],
                ['Urban Structures Pvt Ltd', '29AABFU3333K1Z7', BuyerType::Private, TredsOnboarding::Unknown],
            ],
            invoices: [
                [0, 'INV-2026-101', -60, 540000, InvoiceStatus::Accepted, ['po', 'delivery_ack', 'grn', 'invoice_copy']],
                [0, 'INV-2026-102', -20, 180000, InvoiceStatus::Raised, ['po', 'delivery_ack', 'grn']],
                [1, 'INV-2026-103', -8, 64000, InvoiceStatus::Raised, ['po', 'invoice_copy']],
            ],
        );

        $this->command?->newLine();
        $this->command?->info('Demo logins — password: demo1234');
        $this->command?->line('  sunita@shreeprecision.in   Shree Precision Components (tenant 1)');
        $this->command?->line('  farhan@metrowceramics.in   MetRow Ceramics (tenant 2)');
    }

    /**
     * Build one tenant: business, owner, buyers, and their invoices.
     *
     * @param  array<string, mixed>  $business
     * @param  array{name: string, email: string}  $owner
     * @param  list<array{0: string, 1: string, 2: BuyerType, 3: TredsOnboarding}>  $buyers
     * @param  list<array{0: int, 1: string, 2: int, 3: float|int, 4: InvoiceStatus, 5: list<string>}>  $invoices
     */
    private function workspace(array $business, array $owner, array $buyers, array $invoices): void
    {
        $tenant = Business::create($business);

        $tenant->users()->create([
            'name' => $owner['name'],
            'email' => $owner['email'],
            'password' => 'demo1234',
            'role' => UserRole::Owner,
        ]);

        // Everything below runs with this business bound as the tenant, so the
        // BelongsToTenant hook stamps the right business_id and nothing written
        // here can land in the other demo workspace.
        $this->tenant->run($tenant, function () use ($tenant, $buyers, $invoices) {
            $buyerIds = array_map(
                fn (array $row) => Buyer::create([
                    'name' => $row[0],
                    'gstin' => $row[1],
                    'type' => $row[2],
                    'treds_onboarded' => $row[3],
                ])->id,
                $buyers,
            );

            foreach ($invoices as [$buyerIndex, $number, $offsetDays, $base, $status, $evidence]) {
                $invoiceDate = Carbon::now()->modify("{$offsetDays} days")->toDateString();

                $invoice = $this->workflow->create([
                    'number' => $number,
                    'buyer_id' => $buyerIds[$buyerIndex],
                    'invoice_date' => $invoiceDate,
                    'base_amount' => $base,
                ]);

                foreach ($evidence as $type) {
                    $this->workflow->setEvidence($invoice, EvidenceType::from($type), true);
                }

                $this->advance($invoice, $status, $invoiceDate);
            }

            $this->command?->info(sprintf(
                'Seeded %s — %d invoices, %d buyers.',
                $tenant->name,
                $tenant->invoices()->count(),
                $tenant->buyers()->count(),
            ));
        });
    }

    /**
     * Put a seeded invoice into its demo state with dates that read like a real
     * book: accepted a fortnight after raising, financed three weeks after,
     * settled a little late.
     */
    private function advance(Invoice $invoice, InvoiceStatus $status, string $invoiceDate): void
    {
        if ($status === InvoiceStatus::Raised) {
            return;
        }

        $this->workflow->setStatus($invoice, $status);

        $raised = Carbon::parse($invoiceDate);

        match ($status) {
            InvoiceStatus::Settled => $invoice->forceFill([
                'approval_date' => $raised->copy()->addDays(12)->toDateString(),
                'paid_date' => $raised->copy()->addDays(50)->toDateString(),
            ])->save(),

            InvoiceStatus::Financed => $this->recordDisbursal($invoice, $raised),

            InvoiceStatus::Disputed => $invoice->disputes()->create([
                'forum' => DisputeForum::Msefc,
                'stage' => 'hearing',
                'filed_on' => $raised->copy()->addDays(60)->toDateString(),
                'deadline_on' => $raised->copy()->addDays(90)->toDateString(),
            ]),

            default => $invoice->forceFill([
                'approval_date' => $raised->copy()->addDays(12)->toDateString(),
            ])->save(),
        };
    }

    /**
     * A financed demo invoice needs the money trail behind it, or the page would
     * claim a disbursal it cannot show.
     */
    private function recordDisbursal(Invoice $invoice, Carbon $raised): void
    {
        $invoice->financings()->create([
            'financier' => 'HDFC Bank',
            'discount_rate' => 1.5,
            'amount_disbursed' => $invoice->total_amount,
            'disbursed_on' => $raised->copy()->addDays(20)->toDateString(),
            'status' => 'disbursed',
        ]);

        $invoice->forceFill([
            'approval_date' => $raised->copy()->addDays(12)->toDateString(),
        ])->save();
    }
}
