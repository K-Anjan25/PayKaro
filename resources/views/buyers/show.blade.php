@php
    /*
     * One buyer, and everything owed by them.
     *
     * The numbers all come off the invoices on this page — the same derived values
     * the invoice screen and the claim packet use — so the drill-down cannot tell a
     * different story from the page it was reached from.
     */
    $onboarded = $buyer->treds_onboarded->isOnboarded();
    $unanswered = $buyer->treds_onboarded === App\Enums\TredsOnboarding::Unknown;
@endphp

<x-layouts.app :title="$buyer->name" active="buyers">
    <div class="screen-head">
        <div>
            <a class="pkg-muted" href="{{ route('buyers.index') }}" style="font-size:.8rem;">← All buyers</a>
            <h1 class="pkg-h1" style="margin-top:.4rem;">{{ $buyer->name }}</h1>
            <p class="pkg-sub">
                {{ $buyer->type->label() }} buyer
                @if ($buyer->gstin) · GSTIN {{ $buyer->gstin }} @endif
                @if ($buyer->email) · {{ $buyer->email }} @endif
            </p>
        </div>
        <div class="pkg-row" style="gap:.6rem; flex-wrap:wrap;">
            <x-treds-badge :treds="$buyer->treds_onboarded" />
            @if ($readiness !== null)
                <span class="metric-pill">Readiness {{ $readiness }}%</span>
            @endif
        </div>
    </div>

    <section class="metric-grid metric-grid--3">
        <article class="metric-card metric-card--dark">
            <div class="metric-eyebrow">Outstanding</div>
            <div class="metric-value num">{{ money($outstanding) }}</div>
            <div class="metric-foot">{{ plural($overdue->count(), 'overdue invoice') }} of {{ plural($invoices->count(), 'invoice') }}</div>
        </article>
        <article class="metric-card {{ $interest > 0 ? 'metric-card--brand' : 'metric-card--soft' }}">
            <div class="metric-eyebrow">Statutory interest accrued</div>
            <div class="metric-value num">{{ money($interest) }}</div>
            <div class="metric-foot">
                {{ config('paykaro.interest_multiplier') }}× the {{ config('paykaro.bank_rate') }}% bank rate, daily — Section 16, MSMED Act 2006
            </div>
        </article>
        <article class="metric-card metric-card--soft">
            <div class="metric-eyebrow">TReDS onboarding</div>
            <div class="metric-value" style="font-size:1.5rem;">{{ $buyer->treds_onboarded->label() }}</div>
            <div class="metric-foot">
                @if ($onboarded)
                    Their invoices can be discounted once the evidence trail is complete.
                @elseif ($unanswered)
                    Unanswered. Until it is confirmed, their invoices cannot be discounted.
                @else
                    Not onboarded, so their invoices cannot be discounted.
                @endif
            </div>
        </article>
    </section>

    @if (! $onboarded)
        {{-- The one follow-up worth making, in the buyer's own terms. The 2026
             amendment mandates exchange use for CPSE buyers, which for a supplier
             means "this is not mine to fix by waiting". --}}
        <div class="pkg-callout {{ $unanswered ? 'pkg-callout--gold' : 'pkg-callout--coral' }}">
            <strong>
                @if ($unanswered)
                    Their TReDS onboarding is still an open question.
                @else
                    {{ $buyer->name }} is not onboarded on TReDS.
                @endif
            </strong>
            @if ($buyer->type === App\Enums\BuyerType::Cpse)
                {{-- Sentence per line: a phrase broken across two lines in the template
                     renders with the newline in it, and a copy assertion then fails on a
                     phrase that is visibly present on the page. --}}
                <p style="margin:.4rem 0 0;">
                    The 2026 amendment mandates exchange use for CPSE buyers, so this is a conversation their treasury has already had — ask which exchange they onboard through.
                </p>
            @else
                <p style="margin:.4rem 0 0;">
                    Confirming the status changes whether {{ plural($overdue->count() ?: $invoices->count(), 'invoice') }} can be discounted or only chased. Update it on the buyer record when they answer.
                </p>
            @endif
        </div>
    @endif

    <section class="pkg-card pkg-tablewrap">
        <div class="pkg-cardhead">
            <div>
                <h2 class="pkg-h2">Invoices</h2>
                <p class="pkg-sub">Everything raised against this buyer, newest first. The balance is the face value less recorded payments.</p>
            </div>
            <span class="metric-pill">{{ plural($invoices->count(), 'invoice') }}</span>
        </div>

        @if ($invoices->isEmpty())
            <div class="pkg-empty" style="border:0;">
                <h2 class="pkg-h2">Nothing raised against {{ $buyer->name }} yet.</h2>
                <p class="pkg-sub">
                    @can('create', App\Models\Invoice::class)
                        <a class="pkg-link" href="{{ route('invoices.create') }}">Raise their first invoice</a> to start the clock on this relationship.
                    @else
                        No invoices have been raised against this buyer.
                    @endcan
                </p>
            </div>
        @else
            <table class="pkg-table">
                <thead>
                    <tr>
                        <th>Invoice</th>
                        <th>Status</th>
                        <th>Raised</th>
                        <th>Due</th>
                        <th>Days overdue</th>
                        <th>Interest</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td><a class="pkg-link" href="{{ route('invoices.show', $invoice) }}"><strong>{{ $invoice->number }}</strong></a></td>
                            <td><span class="metric-pill metric-pill--soft">{{ $invoice->status->label() }}</span></td>
                            <td class="num">{{ $invoice->invoice_date?->format('d M Y') ?? '—' }}</td>
                            <td class="num">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                            <td class="num">
                                @if ($invoice->overdueDays() > 0)
                                    <strong style="color:var(--n-coral);">{{ $invoice->overdueDays() }}</strong>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">{{ money($invoice->interest()) }}</td>
                            <td><strong class="num">{{ money($invoice->balance()) }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="board-foot">
            <span>Buyer records are create-only: no edit or delete, so a GSTIN that has been used on a filing cannot be rewritten later.</span>
            <span>
                Open <strong class="num">{{ money($outstanding) }}</strong>
                · interest <strong class="num">{{ money($interest) }}</strong>
                · total <strong class="num">{{ money(round($outstanding + $interest, 2)) }}</strong>
            </span>
        </div>
    </section>
</x-layouts.app>
