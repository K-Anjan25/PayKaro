<x-layouts.app title="Add a buyer" active="buyers">
    <x-page-header title="Add a buyer" subtitle="Buyers are the customers who owe you — invoices always reference one." />

    <form method="post" action="{{ route('buyers.store') }}" class="pkg-card pkg-form">
        @csrf
        <div class="pkg-grid pkg-grid--2">
            <div class="pkg-field pkg-field--full">
                <label class="pkg-label" for="name">Buyer name</label>
                <input class="pkg-input @error('name') pkg-input--invalid @enderror" id="name" name="name"
                       required maxlength="160" value="{{ old('name') }}" placeholder="e.g. Metro Ceramics Ltd">
                @error('name')<span class="pkg-field-error">{{ $message }}</span>@enderror
            </div>

            <div class="pkg-field">
                <label class="pkg-label" for="gstin">GSTIN</label>
                <input class="pkg-input num @error('gstin') pkg-input--invalid @enderror" id="gstin" name="gstin"
                       maxlength="15" value="{{ old('gstin') }}" placeholder="27AABCU9603R1ZX">
                @error('gstin')<span class="pkg-field-error">{{ $message }}</span>@enderror
                <span class="pkg-field-hint">15 characters, as printed on their invoices. Optional, but it's what a forum asks for.</span>
            </div>

            <div class="pkg-field">
                <label class="pkg-label" for="type">Type</label>
                <select class="pkg-input" id="type" name="type">
                    @foreach (App\Enums\BuyerType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('type', 'private') === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('type')<span class="pkg-field-error">{{ $message }}</span>@enderror
            </div>

            <div class="pkg-field pkg-field--full">
                <label class="pkg-label" for="treds_onboarded">TReDS onboarded?</label>
                <select class="pkg-input" id="treds_onboarded" name="treds_onboarded">
                    @foreach (App\Enums\TredsOnboarding::cases() as $onboarding)
                        <option value="{{ $onboarding->value }}" @selected(old('treds_onboarded', 'unknown') === $onboarding->value)>{{ $onboarding->label() }}</option>
                    @endforeach
                </select>
                @error('treds_onboarded')<span class="pkg-field-error">{{ $message }}</span>@enderror
                <span class="pkg-field-hint">
                    "No" means their invoices cannot be discounted on the exchange yet — {{ config('paykaro.tagline') }} keeps it as a follow-up, not a blocker.
                </span>
            </div>
        </div>

        <div class="pkg-form-actions">
            <button class="pkg-btn pkg-btn--primary" type="submit">Add buyer</button>
            <a class="pkg-btn" href="{{ route('buyers.index') }}">Cancel</a>
        </div>
    </form>
</x-layouts.app>
