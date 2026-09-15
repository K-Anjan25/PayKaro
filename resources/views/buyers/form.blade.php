<x-layouts.app title="Add a buyer" active="buyers">
    <div class="screen-head">
        <div>
            <div class="screen-kicker">Customers / New buyer</div>
            <h1 class="pkg-h1">Add New Buyer</h1>
            <p class="pkg-sub">Register an enterprise counterparty for statutory Section 15 compliance tracking and TReDS routing.</p>
        </div>
        <div class="screen-head-actions">
            <span class="metric-pill">Immutable record</span>
            <span class="metric-pill metric-pill--soft">Samadhaan &amp; TReDS live link active</span>
        </div>
    </div>

    <div class="form-shell">
        <form method="post" action="{{ route('buyers.store') }}" class="pkg-card form-main-panel">
            @csrf

            <div class="pkg-grid pkg-grid--2">
                <div class="pkg-field pkg-field--full">
                    <div class="field-head">
                        <label class="pkg-label" for="name">Buyer name</label>
                        <span class="pkg-muted">Legal name as per GST certificate</span>
                    </div>
                    <input class="pkg-input @error('name') pkg-input--invalid @enderror" id="name" name="name"
                           required maxlength="160" value="{{ old('name') }}" placeholder="e.g. Bharat Dynamics Limited or Siemens India">
                    @error('name')<span class="pkg-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="pkg-field pkg-field--full">
                    <div class="field-head">
                        <label class="pkg-label" for="gstin">GSTIN</label>
                        <span class="pkg-muted">15-digit Goods and Services Tax Identification Number</span>
                    </div>
                    <input class="pkg-input num @error('gstin') pkg-input--invalid @enderror" id="gstin" name="gstin"
                           maxlength="15" value="{{ old('gstin') }}" placeholder="27AAACB1234F1Z5">
                    @error('gstin')<span class="pkg-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="pkg-field pkg-field--full">
                    <div class="field-head">
                        <label class="pkg-label" for="email">Accounts payable email</label>
                        <span class="pkg-muted">Where the invoice and its reminders go</span>
                    </div>
                    <input class="pkg-input @error('email') pkg-input--invalid @enderror" id="email" name="email"
                           type="email" maxlength="190" value="{{ old('email') }}" placeholder="ap@buyer.example">
                    @error('email')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    <span class="pkg-field-hint">Optional — you can track an invoice without it. Sending is the one action that needs an address.</span>
                </div>

                <div class="pkg-field pkg-field--full">
                    <label class="pkg-label">Enterprise Classification / Type</label>
                    <div class="choice-grid choice-grid--3">
                        @foreach (App\Enums\BuyerType::cases() as $type)
                            <label class="choice-card">
                                <input type="radio" name="type" value="{{ $type->value }}" @checked(old('type', 'private') === $type->value)>
                                <span class="choice-title">{{ $type->label() }}</span>
                                <span class="choice-copy">
                                    @if ($type === App\Enums\BuyerType::Cpse)
                                        Central public sector enterprise.
                                    @elseif ($type === App\Enums\BuyerType::Psu)
                                        Public sector undertaking.
                                    @else
                                        Private / commercial buyer.
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('type')<span class="pkg-field-error">{{ $message }}</span>@enderror
                </div>

                <div class="pkg-field pkg-field--full">
                    <label class="pkg-label">TReDS onboarded?</label>
                    <div class="choice-grid choice-grid--3">
                        @foreach (App\Enums\TredsOnboarding::cases() as $onboarding)
                            <label class="choice-card choice-card--compact">
                                <input type="radio" name="treds_onboarded" value="{{ $onboarding->value }}" @checked(old('treds_onboarded', 'unknown') === $onboarding->value)>
                                <span class="choice-title">{{ $onboarding->label() }}</span>
                                <span class="choice-copy">
                                    @if ($onboarding === App\Enums\TredsOnboarding::Yes)
                                        Factoring enabled.
                                    @elseif ($onboarding === App\Enums\TredsOnboarding::No)
                                        Direct settlement only.
                                    @else
                                        Status unknown. Follow up later.
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('treds_onboarded')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    <span class="pkg-field-hint">Once saved, a buyer cannot be edited or deleted in the current product.</span>
                </div>
            </div>

            <div class="pkg-form-actions form-actions-spread">
                <div class="pkg-muted">Single creation event. Once saved, buyer records remain append-only.</div>
                <div class="pkg-row" style="gap:.6rem; flex-wrap:wrap;">
                    <a class="pkg-btn" href="{{ route('buyers.index') }}">Cancel</a>
                    <button class="pkg-btn pkg-btn--primary" type="submit">Save</button>
                </div>
            </div>
        </form>

        <aside class="form-side-stack">
            <div class="pkg-card">
                <div class="pkg-cardhead">
                    <h2 class="pkg-h2">MSMED Act 2006 Classification</h2>
                    <span class="metric-pill">Section 15 &amp; 16</span>
                </div>
                <div class="support-stack">
                    <div class="support-card">
                        <strong>Quarterly filing expectations</strong>
                        <p>CPSE and PSU buyers are the ones most often scrutinized for delayed-payment exposure and TReDS obligations.</p>
                    </div>
                    <div class="support-card support-card--inline">
                        <span>Delayed penalty</span>
                        <strong>3× RBI bank rate, monthly rests</strong>
                    </div>
                    <div class="support-card support-card--inline">
                        <span>Statutory ceiling</span>
                        <strong>{{ config('paykaro.msme_due_days') }} days</strong>
                    </div>
                </div>
            </div>

            <div class="pkg-card">
                <div class="pkg-cardhead">
                    <h2 class="pkg-h2">Append-only Registry</h2>
                </div>
                <p class="pkg-sub">In line with the wireframes and current product rules, buyers are create-only. If a legal entity changes, create a fresh successor record rather than mutating history.</p>
            </div>
        </aside>
    </div>
</x-layouts.app>
