@php
    // Only the business owner may change legal identity; team members can still
    // see the details they invoice against.
    $canEditIdentity = auth()->user()->isOwner();
@endphp

<x-layouts.app title="Settings" active="settings">
    <x-page-header title="Settings" subtitle="Your business and bank details — where settled money lands." />

    <form method="post" action="{{ route('settings.update') }}" class="pkg-card pkg-form">
        @csrf
        @method('PUT')

        <div class="pkg-grid pkg-grid--2">
            <div class="pkg-field pkg-field--full">
                <label class="pkg-label" for="name">Business name</label>
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

            <div class="pkg-field">
                <label class="pkg-label" for="udyam_no">Udyam no.</label>
                <input class="pkg-input num @error('udyam_no') pkg-input--invalid @enderror" id="udyam_no" name="udyam_no"
                       value="{{ old('udyam_no', $business->udyam_no) }}" maxlength="19" @disabled(! $canEditIdentity)>
                @error('udyam_no')<span class="pkg-field-error">{{ $message }}</span>@enderror
            </div>

            <div class="pkg-field">
                <label class="pkg-label" for="bank_name">Bank name</label>
                <input class="pkg-input" id="bank_name" name="bank_name" value="{{ old('bank_name', $business->bank_name) }}"
                       maxlength="120" @disabled(! $canEditIdentity)>
                @error('bank_name')<span class="pkg-field-error">{{ $message }}</span>@enderror
            </div>

            <div class="pkg-field">
                <label class="pkg-label" for="bank_acc_no">Account number</label>
                <input class="pkg-input num" id="bank_acc_no" name="bank_acc_no" value="{{ old('bank_acc_no', $business->bank_acc_no) }}"
                       maxlength="20" autocomplete="off" @disabled(! $canEditIdentity)>
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

        @if ($canEditIdentity)
            <div class="pkg-form-actions">
                <button class="pkg-btn pkg-btn--primary" type="submit">Save</button>
            </div>
        @else
            <p class="pkg-sub" style="margin-top:.8rem;">
                Legal and bank details are edited by the business owner — ask
                {{ $business->owner?->name ?? 'the account owner' }} to change them.
            </p>
        @endif
    </form>

    <div class="pkg-card">
        <h2 class="pkg-h2">Account</h2>
        <div class="pkg-statlist">
            <div><span class="pkg-meta">Signed in as</span> <strong>{{ auth()->user()->email }}</strong></div>
            <div style="margin-top:.5rem;">
                <span class="pkg-meta">Role</span> <strong>{{ auth()->user()->role->label() }}</strong>
            </div>
            <div style="margin-top:.5rem;">
                <span class="pkg-meta">Google sign-in</span>
                <strong>{{ auth()->user()->google_id ? 'linked' : 'not linked' }}</strong>
            </div>
        </div>
    </div>
</x-layouts.app>
