@php
    $isEdit = $invoice !== null;
    $dueDays = (int) config('paykaro.msme_due_days');
    $defaultTaxRate = (float) config('paykaro.default_tax_rate');

    $baseValue = (float) old('base_amount', $invoice?->base_amount ?? 0);
    $taxAmountValue = old('tax_amount', $invoice?->tax_amount);
    $taxPercentValue = old('tax_percent');

    if ($taxPercentValue === null && $baseValue > 0 && $taxAmountValue !== null && (float) $taxAmountValue >= 0) {
        $taxPercentValue = round(((float) $taxAmountValue / $baseValue) * 100, 2);
    }

    $initialTotal = $baseValue + (float) ($taxAmountValue ?? ($baseValue * $defaultTaxRate / 100));
@endphp

<x-layouts.app :title="$isEdit ? 'Edit invoice' : 'New invoice'" active="invoices">
    <div class="screen-head">
        <div>
            <div class="screen-kicker">{{ $isEdit ? 'Invoices / Edit invoice' : 'Invoices / New invoice' }}</div>
            <h1 class="pkg-h1">{{ $isEdit ? 'Edit Invoice' : 'Create New Invoice' }}</h1>
            <p class="pkg-sub">Issue an MSME statutory receivable with an auto-computed due date and total.</p>
        </div>
        <div class="screen-head-actions">
            <span class="metric-pill">MSMED Act 2006 engine active</span>
            <span class="metric-pill metric-pill--soft">Draft ID: {{ $invoice?->number ?? 'AUTO-GEN-'.str_pad((string) now()->format('d'), 3, '0', STR_PAD_LEFT) }}</span>
        </div>
    </div>

    @if ($buyers->isEmpty())
        <div class="pkg-card pkg-empty">
            <h2 class="pkg-h2">Add a buyer first</h2>
            <p class="pkg-sub">Invoices always reference a buyer.</p>
            <a class="pkg-btn pkg-btn--primary" href="{{ route('buyers.create') }}">+ Add buyer</a>
        </div>
    @else
        <div class="form-shell">
            <form method="post"
                  action="{{ $isEdit ? route('invoices.update', $invoice) : route('invoices.store') }}"
                  class="pkg-card form-main-panel">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <div class="pkg-grid pkg-grid--2">
                    <div class="pkg-field pkg-field--full">
                        <div class="field-head">
                            <label class="pkg-label" for="number">Invoice number</label>
                            <span class="pkg-muted">Unique tax identification</span>
                        </div>
                        <input class="pkg-input @error('number') pkg-input--invalid @enderror" id="number" name="number"
                               required maxlength="60" value="{{ old('number', $invoice?->number ?? $suggestedNumber) }}"
                               placeholder="e.g. INV-2026-016">
                        @error('number')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="pkg-field pkg-field--full">
                        <div class="field-head">
                            <label class="pkg-label" for="buyer_id">Buyer entity</label>
                            <a class="pkg-btn pkg-btn--link pkg-btn--sm" href="{{ route('buyers.create') }}">+ Add buyer</a>
                        </div>
                        <select class="pkg-input @error('buyer_id') pkg-input--invalid @enderror" id="buyer_id" name="buyer_id" required>
                            @foreach ($buyers as $buyer)
                                <option value="{{ $buyer->id }}" @selected((int) old('buyer_id', $invoice?->buyer_id) === $buyer->id)>
                                    {{ $buyer->name }}@if($buyer->treds_onboarded->isOnboarded()) · TReDS ✓@endif
                                </option>
                            @endforeach
                        </select>
                        @error('buyer_id')<span class="pkg-field-error">{{ $message }}</span>@enderror
                        <span class="pkg-field-hint">Searchable counterparties are available in the Customers directory. This screen keeps an inline escape hatch so you can add one and come back.</span>
                    </div>

                    <div class="pkg-field">
                        <label class="pkg-label" for="invoice_date">Invoice date</label>
                        <input class="pkg-input @error('invoice_date') pkg-input--invalid @enderror" id="invoice_date"
                               type="date" name="invoice_date" required value="{{ old('invoice_date', $invoiceDate) }}"
                               max="{{ now()->toDateString() }}">
                        @error('invoice_date')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="pkg-field">
                        <div class="field-head">
                            <label class="pkg-label" for="due_preview">Statutory due date</label>
                            <span class="pkg-muted">Strict {{ $dueDays }}-day cap</span>
                        </div>
                        <input class="pkg-input form-output" id="due_preview" type="text" readonly
                               value="{{ $invoice?->due_date?->format('d M Y') ?? '' }}">
                        <span class="pkg-field-hint">Auto-calculated from the invoice date. Never manually editable.</span>
                    </div>

                    <div class="pkg-field">
                        <label class="pkg-label" for="base_amount">Base amount (₹)</label>
                        <input class="pkg-input @error('base_amount') pkg-input--invalid @enderror" id="base_amount"
                               name="base_amount" type="number" min="0" step="0.01" required
                               value="{{ old('base_amount', $invoice?->base_amount) }}" placeholder="0.00">
                        @error('base_amount')<span class="pkg-field-error">{{ $message }}</span>@enderror
                        <span class="pkg-field-hint">Exclusive of taxes &amp; duties.</span>
                    </div>

                    <div class="pkg-field">
                        <div class="field-head">
                            <label class="pkg-label" for="tax_percent">Tax — GST (%)</label>
                            <span class="pkg-muted">Blank = auto {{ rtrim(rtrim(number_format($defaultTaxRate, 2, '.', ''), '0'), '.') }}%</span>
                        </div>
                        <input class="pkg-input @error('tax_amount') pkg-input--invalid @enderror" id="tax_percent"
                               name="tax_percent" type="number" min="0" step="0.01"
                               value="{{ $taxPercentValue }}" placeholder="{{ rtrim(rtrim(number_format($defaultTaxRate, 2, '.', ''), '0'), '.') }}">
                        <input type="hidden" name="tax_amount" id="tax_amount" value="{{ $taxAmountValue }}">
                        @error('tax_amount')<span class="pkg-field-error">{{ $message }}</span>@enderror
                        <span class="pkg-field-hint">Enter <strong>0</strong> for no GST. Leave blank to use the default rate.</span>
                    </div>

                    <div class="pkg-field pkg-field--full">
                        <div class="total-card">
                            <div>
                                <div class="metric-eyebrow">Total invoice value (auto-computed)</div>
                                <div class="metric-value num" id="total_display">{{ money($initialTotal) }}</div>
                            </div>
                            <div class="pkg-muted" id="tax_breakdown">CGST + SGST preview</div>
                        </div>
                    </div>

                    <div class="pkg-field pkg-field--full">
                        <label class="pkg-label" for="notes">Notes</label>
                        <textarea class="pkg-input pkg-textarea @error('notes') pkg-input--invalid @enderror" id="notes"
                                  name="notes" maxlength="2000" placeholder="Optional internal memo, purchase-order note, batch or delivery detail.">{{ old('notes', $invoice?->notes) }}</textarea>
                        @error('notes')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="pkg-form-actions form-actions-spread">
                    <a class="pkg-btn" href="{{ $isEdit ? route('invoices.show', $invoice) : route('invoices.index') }}">Cancel</a>
                    <button class="pkg-btn pkg-btn--primary" type="submit">Save</button>
                </div>
            </form>

            <aside class="form-side-stack">
                <div class="pkg-card">
                    <div class="pkg-cardhead">
                        <h2 class="pkg-h2">Statutory Protection</h2>
                        <span class="metric-pill">MSMED Act 2006</span>
                    </div>
                    <div class="support-stack">
                        <div class="support-card">
                            <strong>Section 15: {{ $dueDays }}-Day Payment Window Cap</strong>
                            <p>Where the buyer and supplier agree terms in writing, the payment period must not exceed {{ $dueDays }} days from acceptance or deemed acceptance.</p>
                        </div>
                        <div class="support-card support-card--warm">
                            <strong>Section 16: Penal Interest</strong>
                            <p>Default beyond the due date attracts {{ config('paykaro.interest_multiplier') }} times the prevailing bank rate. PayKaro computes it automatically from the stored dates.</p>
                        </div>
                        <div class="support-card support-card--inline">
                            <span>Current bank rate × markup</span>
                            <strong>{{ rtrim(rtrim(number_format(config('paykaro.bank_rate'), 2, '.', ''), '0'), '.') }}% × {{ config('paykaro.interest_multiplier') }}</strong>
                        </div>
                    </div>
                </div>

                <div class="pkg-card">
                    <div class="pkg-cardhead">
                        <h2 class="pkg-h2">Evidence Checklist</h2>
                        <span class="metric-pill metric-pill--soft">5 rows seeded</span>
                    </div>
                    <div class="support-stack">
                        @foreach (App\Enums\EvidenceType::cases() as $type)
                            <div class="support-card support-card--row">
                                <div>
                                    <strong>{{ $type->label() }}</strong>
                                    <p>{{ $type->shortLabel() }}{{ $type->isRequired() ? ' · Required' : ' · Optional' }}</p>
                                </div>
                                <span class="metric-pill {{ $type->isRequired() ? '' : 'metric-pill--soft' }}">{{ $type->isRequired() ? 'Required' : 'Optional' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>

        <script>
            (function () {
                const dueDays = {{ json_encode($dueDays) }};
                const defaultRate = {{ json_encode($defaultTaxRate) }};
                const issued = document.getElementById('invoice_date');
                const due = document.getElementById('due_preview');
                const base = document.getElementById('base_amount');
                const percent = document.getElementById('tax_percent');
                const taxAmount = document.getElementById('tax_amount');
                const total = document.getElementById('total_display');
                const breakdown = document.getElementById('tax_breakdown');

                const money = (value) => new Intl.NumberFormat('en-IN', {
                    style: 'currency',
                    currency: 'INR',
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }).format(value || 0);

                function updateDueDate() {
                    if (!issued.value) {
                        due.value = '';
                        return;
                    }

                    const day = new Date(issued.value + 'T00:00:00');
                    day.setDate(day.getDate() + dueDays);
                    due.value = day.toLocaleDateString('en-GB', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric'
                    }) + ' (Day ' + dueDays + ')';
                }

                function updateTotals() {
                    const baseAmount = parseFloat(base.value) || 0;
                    const enteredPercent = percent.value.trim();
                    const appliedPercent = enteredPercent === '' ? defaultRate : (parseFloat(enteredPercent) || 0);
                    const gstAmount = +(baseAmount * appliedPercent / 100).toFixed(2);
                    const totalAmount = +(baseAmount + gstAmount).toFixed(2);
                    const half = (appliedPercent / 2).toFixed(2).replace(/\.00$/, '');

                    taxAmount.value = gstAmount.toFixed(2);
                    total.textContent = money(totalAmount);

                    if (appliedPercent === 0) {
                        breakdown.textContent = 'Exempt / zero-rated · total equals base amount';
                    } else if (enteredPercent === '') {
                        breakdown.textContent = 'Auto ' + defaultRate + '% GST · CGST ' + (defaultRate / 2) + '% + SGST ' + (defaultRate / 2) + '%';
                    } else {
                        breakdown.textContent = 'CGST ' + half + '% + SGST ' + half + '% · GST ' + money(gstAmount);
                    }
                }

                issued.addEventListener('input', updateDueDate);
                base.addEventListener('input', updateTotals);
                percent.addEventListener('input', updateTotals);

                updateDueDate();
                updateTotals();
            })();
        </script>
    @endif
</x-layouts.app>
