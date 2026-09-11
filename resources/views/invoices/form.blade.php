@php
    $isEdit = $invoice !== null;
    $dueDays = (int) config('paykaro.msme_due_days');
    $taxRate = (float) config('paykaro.default_tax_rate');
@endphp

<x-layouts.app :title="$isEdit ? 'Edit invoice' : 'Raise an invoice'" active="invoices">
    <x-page-header
        :title="$isEdit ? 'Edit invoice '.$invoice->number : 'Raise an invoice'"
        :subtitle="$isEdit ? 'Adjust the commercial terms; the pipeline and evidence stay where they are.' : 'Terms are prefilled from your workspace settings.'"
    />

    @if ($buyers->isEmpty())
        <div class="pkg-card pkg-empty">
            <h2 class="pkg-h2">Add a buyer first</h2>
            <p class="pkg-sub">Invoices always reference a buyer.</p>
            <a class="pkg-btn pkg-btn--primary" href="{{ route('buyers.create') }}">+ Add buyer</a>
        </div>
    @else
        <form method="post"
              action="{{ $isEdit ? route('invoices.update', $invoice) : route('invoices.store') }}"
              class="pkg-card pkg-form">
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

            <div class="pkg-grid pkg-grid--2">
                <div class="pkg-field">
                    <label class="pkg-label" for="number">Invoice number</label>
                    <input class="pkg-input @error('number') pkg-input--invalid @enderror" id="number" name="number"
                           required maxlength="60" value="{{ old('number', $invoice?->number ?? $suggestedNumber) }}">
                    @error('number')<span class="pkg-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="pkg-field">
                    <label class="pkg-label" for="buyer_id">Buyer</label>
                    <select class="pkg-input @error('buyer_id') pkg-input--invalid @enderror" id="buyer_id" name="buyer_id" required>
                        @foreach ($buyers as $buyer)
                            <option value="{{ $buyer->id }}" @selected((int) old('buyer_id', $invoice?->buyer_id) === $buyer->id)>
                                {{ $buyer->name }}@if($buyer->treds_onboarded->isOnboarded()) · TReDS ✓@endif
                            </option>
                        @endforeach
                    </select>
                    @error('buyer_id')<span class="pkg-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="pkg-field">
                    <label class="pkg-label" for="invoice_date">Invoice date</label>
                    <input class="pkg-input @error('invoice_date') pkg-input--invalid @enderror" id="invoice_date"
                           type="date" name="invoice_date" required value="{{ old('invoice_date', $invoiceDate) }}"
                           oninput="pkDue()">
                    @error('invoice_date')<span class="pkg-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="pkg-field">
                    <label class="pkg-label" for="due_preview">Due date</label>
                    <input class="pkg-input" id="due_preview" type="text" disabled
                           value="{{ $invoice?->due_date?->format('d M Y') ?? $dueDays.' days after the invoice date' }}">
                    <span class="pkg-field-hint">Set by the {{ $dueDays }}-day MSMED window — not editable, so it can't be negotiated.</span>
                </div>

                <div class="pkg-field">
                    <label class="pkg-label" for="base_amount">Base amount (₹)</label>
                    <input class="pkg-input @error('base_amount') pkg-input--invalid @enderror" id="base_amount"
                           name="base_amount" type="number" min="0" step="0.01" required
                           value="{{ old('base_amount', $invoice?->base_amount) }}" oninput="pkTax()">
                    @error('base_amount')<span class="pkg-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="pkg-field">
                    <label class="pkg-label" for="tax_amount">Tax — GST {{ $taxRate }}% (auto)</label>
                    <input class="pkg-input @error('tax_amount') pkg-input--invalid @enderror" id="tax_amount"
                           name="tax_amount" type="number" step="0.01"
                           value="{{ old('tax_amount', $invoice?->tax_amount) }}" oninput="pkTotal()">
                    @error('tax_amount')<span class="pkg-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="pkg-field">
                    <label class="pkg-label" for="pk_total">Total (auto)</label>
                    <input class="pkg-input" id="pk_total" type="text" readonly
                           value="{{ number_format((float) old('base_amount', $invoice?->base_amount) + (float) old('tax_amount', $invoice?->tax_amount), 2, '.', '') }}">
                    <span class="pkg-field-hint">Computed on save as base + tax.</span>
                </div>

                <div class="pkg-field pkg-field--full">
                    <label class="pkg-label" for="notes">Notes</label>
                    <textarea class="pkg-input pkg-textarea @error('notes') pkg-input--invalid @enderror" id="notes"
                              name="notes" maxlength="2000">{{ old('notes', $invoice?->notes) }}</textarea>
                    @error('notes')<span class="pkg-field-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="pkg-form-actions">
                <button class="pkg-btn pkg-btn--primary" type="submit">{{ $isEdit ? 'Save changes' : 'Create invoice' }}</button>
                <a class="pkg-btn" href="{{ $isEdit ? route('invoices.show', $invoice) : route('invoices.index') }}">Cancel</a>
            </div>
        </form>

        <script>
            // Mirrors the server's derivation (tax from the configured GST rate when
            // left blank, total = base + tax) so the form previews the stored figure.
            (function () {
                const rate = {{ json_encode($taxRate) }};
                const dueDays = {{ json_encode($dueDays) }};
                const base = document.getElementById('base_amount');
                const tax = document.getElementById('tax_amount');
                const total = document.getElementById('pk_total');
                const issued = document.getElementById('invoice_date');
                const due = document.getElementById('due_preview');
                const money = (value) => value.toFixed(2);

                // The stored due date is derived server-side; this only previews it.
                function pkDue() {
                    if (!issued.value) {
                        return;
                    }
                    const day = new Date(issued.value + 'T00:00:00');
                    day.setDate(day.getDate() + dueDays);
                    due.value = day.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
                }

                window.pkDue = pkDue;

                function pkTotal() {
                    total.value = money((parseFloat(base.value) || 0) + (parseFloat(tax.value) || 0));
                }

                function pkTax() {
                    if (tax.value === '') {
                        tax.value = money((parseFloat(base.value) || 0) * rate / 100);
                    }
                    pkTotal();
                }

                window.pkTax = pkTax;
                window.pkTotal = pkTotal;

                pkTax();
                pkDue();
            })();
        </script>
    @endif
</x-layouts.app>
