@php
    $canManage = auth()->user()->can('update', $invoice);
    $latestDispute = $invoice->disputes->first();
    $pipeline = App\Enums\InvoiceStatus::pipeline();
    $currentIndex = array_search($invoice->status, $pipeline, true);
    $statusStep = $invoice->status === App\Enums\InvoiceStatus::Disputed
        ? 3
        : (($currentIndex === false ? 1 : $currentIndex + 1));
@endphp

<x-layouts.app :title="'Invoice '.$invoice->number" active="invoices">
    <div class="detail-breadcrumb">
        <a href="{{ route('invoices.index') }}">Invoices</a>
        <span>›</span>
        <strong>{{ $invoice->number }}</strong>
        @if ($invoice->status === App\Enums\InvoiceStatus::Disputed)
            <span class="metric-pill metric-pill--danger">Action required: MSMED clock active</span>
        @endif
    </div>

    <section class="pkg-card invoice-hero">
        <div class="invoice-hero-main">
            <div>
                <div class="invoice-hero-badges">
                    <h1 class="pkg-h1">{{ $invoice->number }}</h1>
                    <x-status-badge :status="$invoice->status" />
                    @if ($invoice->buyer)
                        <span class="metric-pill metric-pill--soft">{{ $invoice->buyer->type->label() }} account</span>
                    @endif
                </div>
                <div class="invoice-hero-sub">
                    <strong>{{ $invoice->buyer?->name ?? 'No buyer linked' }}</strong>
                    <span>GSTIN: {{ $invoice->buyer?->gstin ?: '—' }}</span>
                </div>
                <div class="invoice-hero-sub invoice-hero-sub--minor">
                    <span>Created {{ $invoice->invoice_date?->format('d M Y') }}</span>
                    <span>Due {{ $invoice->due_date?->format('d M Y') }}</span>
                    @if ($invoice->overdueDays() > 0)
                        <span class="metric-pill metric-pill--danger">Overdue {{ $invoice->overdueDays() }} days</span>
                    @endif
                </div>
            </div>

            <div class="invoice-hero-side">
                <div class="metric-eyebrow">Total outstanding balance</div>
                <div class="metric-value num">{{ money($invoice->balance()) }}</div>
                <div class="pkg-muted">Base {{ money($invoice->base_amount) }} + GST {{ money($invoice->tax_amount) }}</div>
                <div class="pkg-row" style="gap:.6rem; margin-top:1rem; flex-wrap:wrap; justify-content:flex-end;">
                    @can('update', $invoice)
                        <a class="pkg-btn" href="{{ route('invoices.edit', $invoice) }}">Edit</a>
                    @endcan
                    <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.claim', $invoice) }}">View claim packet</a>
                </div>
            </div>
        </div>
    </section>

    <section class="pkg-card">
        <div class="pkg-cardhead">
            <div>
                <h2 class="pkg-h2">Lifecycle &amp; Recovery Pipeline</h2>
                <p class="pkg-sub">Statutory invoice trajectory with live checkpoint locking.</p>
            </div>
            <span class="metric-pill">Current status: Stage {{ $statusStep }} of {{ $invoice->status === App\Enums\InvoiceStatus::Disputed ? 5 : 4 }}</span>
        </div>

        <div class="stage-track">
            @foreach ($pipeline as $position => $step)
                @php
                    $done = $currentIndex !== false && $invoice->status !== App\Enums\InvoiceStatus::Disputed && $position < $currentIndex;
                    $active = $invoice->status !== App\Enums\InvoiceStatus::Disputed && $position === $currentIndex;
                    $labelDate = match ($step) {
                        App\Enums\InvoiceStatus::Raised => $invoice->invoice_date?->format('d M Y'),
                        App\Enums\InvoiceStatus::Accepted => $invoice->approval_date?->format('d M Y'),
                        App\Enums\InvoiceStatus::Financed => $invoice->financings->first()?->disbursed_on?->format('d M Y'),
                        App\Enums\InvoiceStatus::Settled => $invoice->paid_date?->format('d M Y'),
                        default => null,
                    };
                @endphp
                <div class="stage-step {{ $done ? 'is-done' : '' }} {{ $active ? 'is-active' : '' }}">
                    <span class="stage-bullet">{{ $done ? '✓' : ($position + 1) }}</span>
                    <strong>{{ $step->label() }}</strong>
                    <span>{{ $labelDate ?: 'Pending' }}</span>
                </div>
            @endforeach
            @if ($invoice->status === App\Enums\InvoiceStatus::Disputed)
                <div class="stage-step is-warning is-active">
                    <span class="stage-bullet">!</span>
                    <strong>Disputed</strong>
                    <span>{{ $latestDispute?->filed_on?->format('d M Y') ?: 'Active' }}</span>
                </div>
            @endif
        </div>

        @if ($canManage)
            <div class="pkg-statusbtns" style="margin-top:1rem;">
                @foreach ([
                    App\Enums\InvoiceStatus::Accepted->value => 'Accept',
                    App\Enums\InvoiceStatus::Financed->value => 'Finance',
                    App\Enums\InvoiceStatus::Settled->value => 'Mark settled',
                    App\Enums\InvoiceStatus::Disputed->value => 'Flag disputed',
                ] as $next => $label)
                    <form method="post" action="{{ route('invoices.status', $invoice) }}" class="pkg-inline">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="{{ $next }}">
                        <button class="pkg-btn pkg-btn--sm {{ $invoice->status->value === $next ? 'is-disabled' : '' }}"
                                type="submit" {{ $invoice->status->value === $next ? 'disabled' : '' }}>{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        @endif
    </section>

    <div class="detail-shell">
        <div>
            {{-- The three messages a receivable needs (BRAND_PLAN §4.1). Each one is
                 computed from this invoice, so what the buyer receives matches what
                 this page shows. Sending needs an address on the buyer record, and
                 the button says which case it is in rather than failing on click. --}}
            @if ($canManage)
                <section class="pkg-card" style="margin-bottom:1rem;">
                    <div class="pkg-cardhead">
                        <div>
                            <h2 class="pkg-h2">Correspondence</h2>
                            <p class="pkg-sub">
                                @if ($invoice->buyer?->email)
                                    Sends to {{ $invoice->buyer->email }} — the figures come from this invoice.
                                @else
                                    No email is on file for {{ $invoice->buyer?->name ?? 'this buyer' }}, so nothing can be sent yet.
                                    <a class="pkg-link" href="{{ route('buyers.create') }}">Add a buyer with an address</a>.
                                @endif
                            </p>
                        </div>
                        <span class="metric-pill {{ $invoice->buyer?->email ? '' : 'metric-pill--danger' }}">
                            {{ $invoice->buyer?->email ? 'Ready to send' : 'Address missing' }}
                        </span>
                    </div>

                    <div class="pkg-statusbtns">
                        <form method="post" action="{{ route('invoices.send', $invoice) }}">
                            @csrf
                            <button class="pkg-btn pkg-btn--primary" type="submit">Email invoice to buyer</button>
                        </form>

                        <form method="post" action="{{ route('invoices.remind', $invoice) }}">
                            @csrf
                            <button class="pkg-btn" type="submit">
                                {{ $invoice->overdueDays() > 0
                                    ? 'Send '.$invoice->overdueDays().'-day reminder'
                                    : 'Send reminder' }}
                            </button>
                        </form>

                        <form method="post" action="{{ route('invoices.request-evidence', $invoice) }}">
                            @csrf
                            <button class="pkg-btn" type="submit">Request pending documents</button>
                        </form>
                    </div>
                </section>
            @endif

            <section class="pkg-card">
                <div class="pkg-cardhead">
                    <div>
                        <h2 class="pkg-h2">Evidence Checklist</h2>
                        <p class="pkg-sub">Institutional documentation proof chain required for financing and statutory recovery.</p>
                    </div>
                    <span class="metric-pill">{{ $invoice->presentRequiredEvidence() }} of {{ $invoice->requiredEvidenceCount() }} required</span>
                </div>

                <div class="evidence-stack">
                    @foreach ($checklist as $row)
                        <form method="post" action="{{ route('invoices.evidence', $invoice) }}" class="evidence-row">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="type" value="{{ $row->type->value }}">
                            <input type="hidden" name="present" value="{{ $row->present ? 0 : 1 }}">
                            <div>
                                <div class="evidence-title">{{ $row->type->label() }}</div>
                                <div class="pkg-muted">{{ $row->type->shortLabel() }} · {{ $row->type->isRequired() ? 'Required' : 'Optional' }}</div>
                            </div>
                            <div class="evidence-actions">
                                <span class="metric-pill {{ $row->present ? '' : 'metric-pill--danger' }}">{{ $row->present ? 'Attachment present' : 'Missing' }}</span>
                                @if ($canManage)
                                    <button class="pkg-btn pkg-btn--sm" type="submit">{{ $row->present ? 'Untick' : 'Mark present' }}</button>
                                @endif
                            </div>
                        </form>
                    @endforeach
                </div>
            </section>

            <section class="pkg-card">
                <div class="pkg-cardhead">
                    <div>
                        <h2 class="pkg-h2">Dispute / Claim</h2>
                        <p class="pkg-sub">The filing deadline is computed from the due date; only the forum is chosen here.</p>
                    </div>
                    <span class="metric-pill {{ $latestDispute ? 'metric-pill--danger' : '' }}">{{ $latestDispute ? 'Section 18 ready' : 'No dispute filed' }}</span>
                </div>

                <div class="claim-banner">
                    <div>
                        <strong>Filing deadline: {{ $latestDispute?->deadline_on?->format('d M Y') ?? '—' }}</strong>
                        <p>{{ $latestDispute?->deadlineLabel() ?? 'The deadline will appear once a claim exists.' }}</p>
                    </div>
                    <div class="claim-banner-amount">
                        {{-- Says how it got there: the packet argues the schedule, and a
                             member should be able to see the method on the invoice too. --}}
                        <span>Accrued interest · Section 16, monthly rests</span>
                        <strong class="num">{{ money($invoice->interest()) }}</strong>
                    </div>
                </div>

                @if ($canManage && $invoice->canBeDisputed())
                    <form method="post" action="{{ route('invoices.disputes.store', $invoice) }}" class="claim-form-row">
                        @csrf
                        <div class="pkg-field" style="margin:0; flex:1;">
                            <label class="pkg-label" for="forum">Select forum</label>
                            <select class="pkg-input" id="forum" name="forum">
                                @foreach (App\Enums\DisputeForum::cases() as $forum)
                                    <option value="{{ $forum->value }}">{{ $forum->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="pkg-btn pkg-btn--primary" type="submit">Start claim</button>
                    </form>
                @else
                    <a class="pkg-btn pkg-btn--primary" href="{{ route('invoices.claim', $invoice) }}">Open claim packet</a>
                @endif
            </section>

            <section class="pkg-card">
                <div class="pkg-cardhead">
                    <div>
                        <h2 class="pkg-h2">Payments</h2>
                        <p class="pkg-sub">Recorded receipts against this invoice.</p>
                    </div>
                </div>

                @if ($invoice->payments->isNotEmpty())
                    <ul class="pkg-list">
                        @foreach ($invoice->payments as $payment)
                            <li>
                                <span>{{ $payment->paid_on?->format('d M Y') }}</span>
                                <span>{{ $payment->method ?: 'Payment' }}</span>
                                <span class="pkg-muted">{{ $payment->reference ?: 'No reference' }}</span>
                                <span class="pkg-list-amt num">{{ money($payment->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="pkg-sub">No settlements logged yet. Full invoice balance is still outstanding.</p>
                @endif

                @if ($canManage && $invoice->status !== App\Enums\InvoiceStatus::Settled)
                    <hr class="pkg-divider">
                    <form method="post" action="{{ route('invoices.payments.store', $invoice) }}" class="claim-form-row claim-form-row--grid">
                        @csrf
                        <div class="pkg-field" style="margin:0;">
                            <label class="pkg-label" for="payment_amount">Amount (₹)</label>
                            <input class="pkg-input num" id="payment_amount" name="amount" type="number" step="0.01" min="0"
                                   max="{{ $invoice->balance() }}" value="{{ old('amount', round($invoice->balance(), 2)) }}" required>
                        </div>
                        <div class="pkg-field" style="margin:0;">
                            <label class="pkg-label" for="payment_paid_on">Paid on</label>
                            <input class="pkg-input" id="payment_paid_on" name="paid_on" type="date"
                                   max="{{ now()->toDateString() }}" value="{{ old('paid_on', now()->toDateString()) }}">
                        </div>
                        <div class="pkg-field" style="margin:0;">
                            <label class="pkg-label" for="payment_method">Method</label>
                            <input class="pkg-input" id="payment_method" name="method" value="{{ old('method') }}" placeholder="NEFT / RTGS / UPI">
                        </div>
                        <div class="pkg-field" style="margin:0;">
                            <label class="pkg-label" for="payment_reference">Reference</label>
                            <input class="pkg-input" id="payment_reference" name="reference" value="{{ old('reference') }}" placeholder="UTR / cheque">
                        </div>
                        <button class="pkg-btn pkg-btn--primary" type="submit">Record payment</button>
                    </form>
                @endif
            </section>

            <section class="pkg-card">
                <div class="pkg-cardhead">
                    <div>
                        <h2 class="pkg-h2">Financing &amp; TReDS</h2>
                        <p class="pkg-sub">Discounting, financing history and platform readiness.</p>
                    </div>
                    <x-treds-badge :treds="$invoice->treds()" />
                </div>

                @if ($invoice->financings->isNotEmpty())
                    <ul class="pkg-list">
                        @foreach ($invoice->financings as $financing)
                            <li>
                                <span>{{ $financing->financier }}</span>
                                <span class="pkg-muted">{{ $financing->disbursed_on?->format('d M Y') }} · {{ rtrim(rtrim(number_format($financing->discount_rate, 2, '.', ''), '0'), '.') }}%</span>
                                <span class="pkg-list-amt num">{{ money($financing->amount_disbursed) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="pkg-sub">{{ $invoice->treds() === App\Enums\TredsStatus::PendingBuyerOnboard ? 'Buyer onboarding on TReDS is still pending.' : 'No financing has been recorded yet.' }}</p>
                @endif

                @if ($canManage && $invoice->canBeFinanced())
                    <hr class="pkg-divider">
                    <form method="post" action="{{ route('invoices.financings.store', $invoice) }}" class="claim-form-row claim-form-row--grid">
                        @csrf
                        <div class="pkg-field" style="margin:0;">
                            <label class="pkg-label" for="financier">Financier</label>
                            <input class="pkg-input" id="financier" name="financier" value="{{ old('financier') }}" placeholder="RXIL / bank / NBFC">
                        </div>
                        <div class="pkg-field" style="margin:0;">
                            <label class="pkg-label" for="discount_rate">Discount rate</label>
                            <input class="pkg-input num" id="discount_rate" name="discount_rate" type="number" step="0.1" min="0" max="100" value="{{ old('discount_rate', 1.5) }}">
                        </div>
                        <div class="pkg-field" style="margin:0;">
                            <label class="pkg-label" for="amount_disbursed">Amount disbursed (₹)</label>
                            <input class="pkg-input num" id="amount_disbursed" name="amount_disbursed" type="number" step="0.01" min="0" value="{{ old('amount_disbursed', round($invoice->total_amount, 2)) }}">
                        </div>
                        <div class="pkg-field" style="margin:0;">
                            <label class="pkg-label" for="financing_disbursed_on">Disbursed on</label>
                            <input class="pkg-input" id="financing_disbursed_on" name="disbursed_on" type="date"
                                   max="{{ now()->toDateString() }}" value="{{ old('disbursed_on', now()->toDateString()) }}">
                        </div>
                        <button class="pkg-btn pkg-btn--primary" type="submit">Finance this</button>
                    </form>
                @endif
            </section>
        </div>

        <aside class="detail-side-stack">
            <section class="pkg-card">
                <div class="metric-eyebrow">Litigation &amp; factoring</div>
                <div class="readiness-ring readiness-ring--panel" style="margin:.8rem 0 1rem;">
                    <svg viewBox="0 0 120 120" aria-hidden="true">
                        <circle cx="60" cy="60" r="48" pathLength="100"></circle>
                        <circle class="ring-progress" cx="60" cy="60" r="48" pathLength="100" style="stroke-dasharray: {{ $invoice->readiness() }}, 100"></circle>
                    </svg>
                    <div class="readiness-ring-copy"><strong>{{ $invoice->readiness() }}</strong><span>/100</span></div>
                </div>
                <h2 class="pkg-h2">Claim Readiness Score</h2>
                <p class="pkg-sub" style="margin-top:.6rem;">{{ $invoice->isFinanceReady() ? 'Evidence and buyer onboarding are in good shape.' : 'The score rises as required evidence is ticked and TReDS blockers are cleared.' }}</p>
                <div class="support-card support-card--warm" style="margin-top:1rem;">
                    <strong>Why is it not 100?</strong>
                    <p>{{ $invoice->readiness() >= 100 ? 'Nothing is held back.' : 'Every unticked required evidence row and every buyer onboarding gap lowers the score.' }}</p>
                </div>
            </section>

            <section class="pkg-card">
                <div class="pkg-cardhead">
                    <h2 class="pkg-h2">Buyer Profile</h2>
                    <a class="pkg-btn pkg-btn--sm pkg-btn--ghost" href="{{ route('buyers.index') }}">/buyers</a>
                </div>
                <strong>{{ $invoice->buyer?->name ?? '—' }}</strong>
                <div class="support-stack" style="margin-top:1rem;">
                    <div class="support-card support-card--inline"><span>Entity type</span><strong>{{ $invoice->buyer?->type->label() ?? '—' }}</strong></div>
                    <div class="support-card support-card--inline"><span>TReDS onboarded</span><strong>{{ $invoice->buyer?->treds_onboarded->label() ?? 'Unknown' }}</strong></div>
                    <div class="support-card support-card--inline"><span>GSTIN</span><strong>{{ $invoice->buyer?->gstin ?: '—' }}</strong></div>
                    <div class="support-card support-card--inline"><span>Total exposure</span><strong class="num">{{ money($invoice->balance()) }}</strong></div>
                </div>
            </section>

            @if ($invoice->notes)
                <section class="pkg-card">
                    <h2 class="pkg-h2">Audit &amp; Legal Notes</h2>
                    <p class="pkg-sub" style="white-space:pre-line; margin-top:.8rem;">{{ $invoice->notes }}</p>
                </section>
            @endif
        </aside>
    </div>
</x-layouts.app>
