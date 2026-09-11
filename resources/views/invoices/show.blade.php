@php
    $canManage = auth()->user()->can('update', $invoice);
@endphp

<x-layouts.app :title="'Invoice '.$invoice->number" active="invoices">
    <x-page-header
        :title="'Invoice '.$invoice->number"
        :subtitle="($invoice->buyer?->name ?? 'No buyer').' · '.($invoice->invoice_date?->format('d M Y') ?? '—')"
    >
        <a class="pkg-btn pkg-btn--ghost" href="{{ route('invoices.index') }}">← All invoices</a>
        @can('update', $invoice)
            <a class="pkg-btn pkg-btn--sm" href="{{ route('invoices.edit', $invoice) }}">✎ Edit</a>
        @endcan
    </x-page-header>

    <div class="pkg-detail-grid">
        <div class="pkg-col-main">
            {{-- Headline figures + pipeline --}}
            <div class="pkg-card">
                <div class="pkg-invoicemeta">
                    <div><span class="pkg-meta">Status</span> <x-status-badge :status="$invoice->status" /></div>
                    <div>
                        <span class="pkg-meta">Due date</span>
                        <strong>{{ $invoice->due_date?->format('d M Y') }}</strong>
                        @if ($invoice->overdueDays() > 0)
                            <span class="pkg-neg">+{{ $invoice->overdueDays() }}d</span>
                        @endif
                    </div>
                    <div><span class="pkg-meta">Balance due</span> <strong class="num">{{ money($invoice->balance()) }}</strong></div>
                    <div>
                        <span class="pkg-meta">Interest accruing</span>
                        <strong class="num {{ $invoice->interest() > 0 ? 'pkg-neg' : '' }}">{{ money($invoice->interest()) }}</strong>
                    </div>
                </div>
                <div class="pkg-amounts">
                    <div class="pkg-amount"><span class="pkg-meta">Base</span><span class="num">{{ money($invoice->base_amount) }}</span></div>
                    <div class="pkg-amount"><span class="pkg-meta">Tax</span><span class="num">{{ money($invoice->tax_amount) }}</span></div>
                    <div class="pkg-amount"><span class="pkg-meta">Total</span><span class="num">{{ money($invoice->total_amount) }}</span></div>
                </div>
                <div style="margin-top:1.2rem;">
                    <x-status-timeline :status="$invoice->status" />
                </div>
            </div>

            {{-- Pipeline transitions --}}
            @if ($canManage)
                <div class="pkg-card">
                    <h2 class="pkg-h2">Move it forward</h2>
                    <div class="pkg-statusbtns">
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
                </div>
            @endif

            {{-- Evidence checklist --}}
            <div class="pkg-card">
                <div class="pkg-cardhead">
                    <h2 class="pkg-h2">Evidence checklist</h2>
                    <span class="pkg-filter" style="padding:.35rem .6rem;">
                        {{ $invoice->presentRequiredEvidence() }} / {{ $invoice->requiredEvidenceCount() }} in
                    </span>
                </div>
                <div class="pkg-evidence">
                    @foreach ($checklist as $row)
                        <form method="post" action="{{ route('invoices.evidence', $invoice) }}" class="pkg-evi">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="type" value="{{ $row->type->value }}">
                            <input type="hidden" name="present" value="{{ $row->present ? 0 : 1 }}">
                            <button class="pkg-check {{ $row->present ? 'is-checked' : '' }}" type="submit"
                                    aria-pressed="{{ $row->present ? 'true' : 'false' }}">
                                <span class="pkg-check-box">{{ $row->present ? '✓' : '' }}</span>
                                <span>{{ $row->type->label() }}</span>
                            </button>
                        </form>
                    @endforeach
                </div>
                @if ($canManage)
                    <p class="pkg-sub" style="margin:.7rem 0 0;">Tick each document only once it's actually in your files — the readiness score and the claim packet both read this list.</p>
                @endif
            </div>

            {{-- Payments --}}
            <div class="pkg-card">
                <h2 class="pkg-h2">Payments</h2>
                @if ($invoice->payments->isNotEmpty())
                    <ul class="pkg-list">
                        @foreach ($invoice->payments as $payment)
                            <li>
                                <span class="pkg-muted">{{ $payment->paid_on?->format('d M Y') }}</span>
                                <span>{{ $payment->method ?: 'Payment' }}</span>
                                <span class="pkg-muted">{{ $payment->reference }}</span>
                                <span class="pkg-list-amt num">{{ money($payment->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="pkg-sub">No payments recorded yet.</p>
                @endif

                @if ($canManage && $invoice->status !== App\Enums\InvoiceStatus::Settled)
                    <hr class="pkg-divider">
                    <form method="post" action="{{ route('invoices.payments.store', $invoice) }}"
                          class="pkg-grid pkg-grid--2" style="gap:.6rem;align-items:end;">
                        @csrf
                        <div class="pkg-field" style="margin:0">
                            <label class="pkg-label" for="payment_amount">Amount (₹)</label>
                            <input class="pkg-input num" id="payment_amount" name="amount" type="number" step="0.01" min="0"
                                   max="{{ $invoice->balance() }}" value="{{ old('amount', round($invoice->balance(), 2)) }}" required>
                            @error('amount')<span class="pkg-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pkg-field" style="margin:0">
                            <label class="pkg-label" for="payment_paid_on">Paid on</label>
                            <input class="pkg-input" id="payment_paid_on" name="paid_on" type="date" value="{{ old('paid_on', now()->toDateString()) }}">
                            @error('paid_on')<span class="pkg-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pkg-field" style="margin:0">
                            <label class="pkg-label" for="payment_method">Method</label>
                            <input class="pkg-input" id="payment_method" name="method" placeholder="NEFT / UPI / Cheque" value="{{ old('method') }}" maxlength="40">
                        </div>
                        <div class="pkg-field" style="margin:0">
                            <label class="pkg-label" for="payment_reference">Ref</label>
                            <input class="pkg-input" id="payment_reference" name="reference" placeholder="UTR" value="{{ old('reference') }}" maxlength="80">
                        </div>
                        <div>
                            <button class="pkg-btn pkg-btn--primary pkg-btn--sm" type="submit">Record payment</button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- Financing --}}
            <div class="pkg-card">
                <h2 class="pkg-h2">Financing &amp; TReDS</h2>
                @if ($invoice->financings->isNotEmpty())
                    <ul class="pkg-list">
                        @foreach ($invoice->financings as $financing)
                            <li>
                                <span>{{ $financing->financier }}</span>
                                <span class="pkg-muted">{{ $financing->disbursed_on?->format('d M Y') }} · {{ rtrim(rtrim(number_format($financing->discount_rate, 2, '.', ''), '0'), '.') }}% discount</span>
                                <span class="pkg-list-amt num">{{ money($financing->amount_disbursed) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="pkg-sub">
                        {{ $invoice->treds() === App\Enums\TredsStatus::PendingBuyerOnboard
                            ? 'Buyer is not on TReDS — confirm their onboarding to unlock financing.'
                            : 'No financing yet.' }}
                    </p>
                @endif

                @if ($canManage && $invoice->canBeFinanced())
                    <hr class="pkg-divider">
                    <form method="post" action="{{ route('invoices.financings.store', $invoice) }}"
                          class="pkg-grid pkg-grid--3" style="gap:.6rem;align-items:end;">
                        @csrf
                        <div class="pkg-field" style="margin:0">
                            <label class="pkg-label" for="financier">Financier</label>
                            <input class="pkg-input" id="financier" name="financier" placeholder="Bank / NBFC" value="{{ old('financier') }}" maxlength="120">
                            @error('financier')<span class="pkg-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pkg-field" style="margin:0">
                            <label class="pkg-label" for="discount_rate">Discount %</label>
                            <input class="pkg-input num" id="discount_rate" name="discount_rate" type="number" step="0.1" min="0" max="100" value="{{ old('discount_rate', 1.5) }}">
                            @error('discount_rate')<span class="pkg-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div class="pkg-field" style="margin:0">
                            <label class="pkg-label" for="amount_disbursed">Disbursed (₹)</label>
                            <input class="pkg-input num" id="amount_disbursed" name="amount_disbursed" type="number" step="0.01" min="0" value="{{ old('amount_disbursed', round($invoice->total_amount, 2)) }}">
                            @error('amount_disbursed')<span class="pkg-field-error">{{ $message }}</span>@enderror
                        </div>
                        <div>
                            <button class="pkg-btn pkg-btn--primary pkg-btn--sm" type="submit">Finance this</button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- Disputes --}}
            <div class="pkg-card">
                <h2 class="pkg-h2">Dispute / claim</h2>
                @foreach ($invoice->disputes as $dispute)
                    <div class="pkg-callout pkg-callout--coral" style="margin-bottom:.7rem;">
                        <strong>{{ strtoupper($dispute->forum->value) }} claim</strong>
                        filed {{ $dispute->filed_on?->format('d M Y') }} · stage {{ $dispute->stage }}
                        @if ($dispute->deadline_on)
                            <small>Deadline {{ $dispute->deadline_on->format('d M Y') }} — {{ $dispute->deadlineLabel() }}</small>
                        @endif
                    </div>
                @endforeach

                @if ($canManage && $invoice->canBeDisputed())
                    <form method="post" action="{{ route('invoices.disputes.store', $invoice) }}"
                          class="pkg-row" style="gap:.6rem;flex-wrap:wrap;">
                        @csrf
                        <select class="pkg-input" name="forum" style="width:auto;" aria-label="Forum">
                            @foreach (App\Enums\DisputeForum::cases() as $forum)
                                <option value="{{ $forum->value }}">{{ $forum->label() }}</option>
                            @endforeach
                        </select>
                        <button class="pkg-btn pkg-btn--sm" type="submit">Start claim</button>
                        <a class="pkg-btn pkg-btn--sm pkg-btn--ghost" href="{{ route('invoices.claim', $invoice) }}">Preview evidence packet</a>
                    </form>
                @else
                    <a class="pkg-btn pkg-btn--sm pkg-btn--ghost" href="{{ route('invoices.claim', $invoice) }}">View evidence packet</a>
                @endif
            </div>
        </div>

        {{-- Side rail --}}
        <div class="pkg-col-side">
            <div class="pkg-card">
                <h2 class="pkg-h2">Readiness</h2>
                <div class="pkg-score-big">{{ $invoice->readiness() }}<span>/100</span></div>
                <div style="margin:.5rem 0;">
                    <x-progress :score="$invoice->readiness()" :show-label="false" />
                </div>
                <p class="pkg-sub">
                    {{ match ($invoice->treds()) {
                        App\Enums\TredsStatus::Ready => 'Finance-ready — evidence in, buyer on TReDS.',
                        App\Enums\TredsStatus::PendingBuyerOnboard => 'Buyer not on TReDS — confirm before this churns.',
                        App\Enums\TredsStatus::Financed => 'Funded — keep the discharge proof with the file.',
                        App\Enums\TredsStatus::Ineligible => 'Disputed, so it is off the financing board for now.',
                        default => 'Not currently financeable.',
                    } }}
                </p>
                <div class="pkg-score-note">
                    <span class="pkg-meta">Buyer TReDS</span>
                    <strong>{{ $invoice->buyer?->treds_onboarded?->label() ?? 'Unknown' }}</strong>
                </div>
            </div>

            <div class="pkg-card">
                <h2 class="pkg-h2">Buyer</h2>
                <div>
                    <span class="pkg-meta">Name</span>
                    <strong>{{ $invoice->buyer?->name ?? '—' }}</strong>
                </div>
                @if ($invoice->buyer)
                    <div style="margin-top:.5rem;">
                        <span class="pkg-meta">Type</span>
                        <span class="pkg-badge pkg-badge--neutral">{{ $invoice->buyer->type->label() }}</span>
                    </div>
                    <div style="margin-top:.5rem;">
                        <span class="pkg-meta">TReDS</span>
                        <x-treds-badge :treds="$invoice->buyer->treds_onboarded" />
                    </div>
                @endif
                <div style="margin-top:.9rem;">
                    <a class="pkg-btn pkg-btn--sm pkg-btn--ghost" href="{{ route('buyers.index') }}">Manage buyers</a>
                </div>
            </div>

            @if ($invoice->notes)
                <div class="pkg-card">
                    <h2 class="pkg-h2">Notes</h2>
                    <p class="pkg-sub" style="white-space:pre-line;">{{ $invoice->notes }}</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
