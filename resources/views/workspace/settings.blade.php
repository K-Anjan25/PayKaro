@php
    $canEditIdentity = auth()->user()->isOwner();
    $owner = $business->owner;
@endphp

<x-layouts.app title="Settings" active="settings">
    <div class="screen-head">
        <div>
            <div class="screen-kicker">Administration / Organisational settings</div>
            <h1 class="pkg-h1">Institutional Profile &amp; Compliance</h1>
            <p class="pkg-sub">Manage corporate identity, GSTIN, Udyam, bank credentials and the role boundary for your statutory workspace.</p>
        </div>
        <div class="screen-head-actions">
            <span class="metric-pill">Role preview: {{ $canEditIdentity ? 'Owner' : auth()->user()->role->label().' (read-only)' }}</span>
        </div>
    </div>

    <div class="settings-shell">
        <div>
            <div class="pkg-card settings-banner {{ $canEditIdentity ? '' : 'settings-banner--warning' }}">
                <div>
                    <strong>{{ $canEditIdentity ? 'Owner privileges active' : 'Role-based access control: read-only audit clearance' }}</strong>
                    <p>{{ $canEditIdentity ? 'You can update the business identity that invoices, payments and claims rely on.' : 'Your role may view GSTIN, PAN, Udyam and bank details, but only the business owner may change them.' }}</p>
                </div>
                <span class="metric-pill {{ $canEditIdentity ? '' : 'metric-pill--danger' }}">{{ $canEditIdentity ? 'Editable' : '403 on submit' }}</span>
            </div>

            <form method="post" action="{{ route('settings.update') }}" class="pkg-card form-main-panel">
                @csrf
                @method('PUT')

                <div class="pkg-cardhead">
                    <div>
                        <h2 class="pkg-h2">Legal Entity &amp; Banking Parameters</h2>
                        <p class="pkg-sub">The exact identity your invoices, receipts and claim packets are issued against.</p>
                    </div>
                    <span class="metric-pill">{{ $canEditIdentity ? 'Verified' : 'Read-only' }}</span>
                </div>

                <div class="pkg-grid pkg-grid--2">
                    <div class="pkg-field pkg-field--full">
                        <div class="field-head">
                            <label class="pkg-label" for="name">Business name</label>
                            <span class="pkg-muted">Registered trade name</span>
                        </div>
                        <input class="pkg-input @error('name') pkg-input--invalid @enderror" id="name" name="name"
                               value="{{ old('name', $business->name) }}" maxlength="160" @required($canEditIdentity) @disabled(! $canEditIdentity)>
                        @error('name')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="pkg-field">
                        <label class="pkg-label" for="gstin">GSTIN</label>
                        <input class="pkg-input num @error('gstin') pkg-input--invalid @enderror" id="gstin" name="gstin"
                               value="{{ old('gstin', $business->gstin) }}" maxlength="15" @disabled(! $canEditIdentity)>
                        @error('gstin')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="pkg-field">
                        <label class="pkg-label" for="pan">PAN</label>
                        <input class="pkg-input num @error('pan') pkg-input--invalid @enderror" id="pan" name="pan"
                               value="{{ old('pan', $business->pan) }}" maxlength="10" @disabled(! $canEditIdentity)>
                        @error('pan')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="pkg-field pkg-field--full">
                        <label class="pkg-label" for="udyam_no">Udyam no.</label>
                        <input class="pkg-input num @error('udyam_no') pkg-input--invalid @enderror" id="udyam_no" name="udyam_no"
                               value="{{ old('udyam_no', $business->udyam_no) }}" maxlength="19" @disabled(! $canEditIdentity)>
                        @error('udyam_no')<span class="pkg-field-error">{{ $message }}</span>@enderror
                        <span class="pkg-field-hint">This identifier is what delayed-payment protections and public-sector claims are generally keyed to.</span>
                    </div>

                    <div class="pkg-field">
                        <label class="pkg-label" for="bank_name">Bank name</label>
                        <input class="pkg-input @error('bank_name') pkg-input--invalid @enderror" id="bank_name" name="bank_name"
                               value="{{ old('bank_name', $business->bank_name) }}" maxlength="120" @disabled(! $canEditIdentity)>
                        @error('bank_name')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="pkg-field">
                        <label class="pkg-label" for="bank_acc_no">Account number</label>
                        <input class="pkg-input num @error('bank_acc_no') pkg-input--invalid @enderror" id="bank_acc_no" name="bank_acc_no"
                               value="{{ old('bank_acc_no', $business->bank_acc_no) }}" maxlength="20" autocomplete="off" @disabled(! $canEditIdentity)>
                        @error('bank_acc_no')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="pkg-field">
                        <label class="pkg-label" for="bank_ifsc">IFSC</label>
                        <input class="pkg-input num @error('bank_ifsc') pkg-input--invalid @enderror" id="bank_ifsc" name="bank_ifsc"
                               value="{{ old('bank_ifsc', $business->bank_ifsc) }}" maxlength="11" @disabled(! $canEditIdentity)>
                        @error('bank_ifsc')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="pkg-field">
                        <label class="pkg-label" for="treds_registered">TReDS registered?</label>
                        <select class="pkg-input" id="treds_registered" name="treds_registered" @disabled(! $canEditIdentity)>
                            <option value="0" @selected(! old('treds_registered', $business->treds_registered))>No</option>
                            <option value="1" @selected((bool) old('treds_registered', $business->treds_registered))>Yes</option>
                        </select>
                        @error('treds_registered')<span class="pkg-field-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="pkg-form-actions form-actions-spread">
                    <span class="pkg-muted">Cryptographically logged to the immutable audit trail.</span>
                    @if ($canEditIdentity)
                        <button class="pkg-btn pkg-btn--primary" type="submit">Save</button>
                    @else
                        <button class="pkg-btn" type="submit" disabled>Save changes</button>
                    @endif
                </div>
            </form>
        </div>

        <aside class="settings-side-stack">
            <div class="pkg-card">
                <div class="pkg-cardhead">
                    <h2 class="pkg-h2">Workspace Operator</h2>
                    <span class="metric-pill">{{ auth()->user()->role->label() }}</span>
                </div>
                <div class="settings-usercard">
                    <div class="app-avatar app-avatar--fallback">{{ auth()->user()->initials() }}</div>
                    <div>
                        <strong>{{ auth()->user()->name }}</strong>
                        <div class="pkg-muted">{{ auth()->user()->email }}</div>
                    </div>
                </div>
                <div class="support-stack" style="margin-top:1rem;">
                    <div class="support-card support-card--inline">
                        <span>Primary owner</span>
                        <strong>{{ $owner?->name ?? '—' }}</strong>
                    </div>
                    <div class="support-card support-card--inline">
                        <span>Google sign-in</span>
                        <strong>{{ auth()->user()->google_id ? 'Linked' : 'Not linked' }}</strong>
                    </div>
                </div>
            </div>

            <div class="pkg-card">
                <div class="pkg-cardhead">
                    <h2 class="pkg-h2">Statutory Vault</h2>
                    <span class="metric-pill">MSMED Act 2006</span>
                </div>
                <div class="support-stack">
                    <div class="support-card support-card--inline">
                        <span>Section 15 &amp; 16 protection</span>
                        <strong>Enforced</strong>
                    </div>
                    <div class="support-card support-card--inline">
                        <span>Benchmark bank rate</span>
                        <strong>{{ rtrim(rtrim(number_format(config('paykaro.bank_rate'), 2, '.', ''), '0'), '.') }}% p.a.</strong>
                    </div>
                    <div class="support-card support-card--inline">
                        <span>Statutory penal rate</span>
                        <strong>{{ rtrim(rtrim(number_format(config('paykaro.bank_rate') * config('paykaro.interest_multiplier'), 2, '.', ''), '0'), '.') }}% p.a.</strong>
                    </div>
                    <div class="support-card support-card--inline">
                        <span>Business TReDS registration</span>
                        <strong>{{ $business->treds_registered ? 'Registered' : 'Not registered' }}</strong>
                    </div>
                </div>
            </div>

            <div class="pkg-card">
                <div class="pkg-cardhead">
                    <h2 class="pkg-h2">Statutory Documents</h2>
                </div>
                <ul class="pkg-list">
                    <li><span>Udyam registration</span><span class="pkg-list-amt">{{ $business->udyam_no ?: 'Not recorded' }}</span></li>
                    <li><span>GSTIN active status</span><span class="pkg-list-amt">{{ $business->gstin ?: 'Not recorded' }}</span></li>
                    <li><span>Settlement bank IFSC</span><span class="pkg-list-amt">{{ $business->bank_ifsc ?: 'Not recorded' }}</span></li>
                </ul>
            </div>
        </aside>
    </div>
</x-layouts.app>
