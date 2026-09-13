<?php

namespace App\Http\Requests\Buyers;

use App\Enums\BuyerType;
use App\Enums\TredsOnboarding;
use App\Models\Buyer;
use App\Rules\Gstin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Adding a customer.
 *
 * GSTIN gets the real 15-character format check (2 digits, 5 letters, 6
 * alphanumeric, Z, 1 alphanumeric) because a wrong GSTIN on a buyer record
 * quietly breaks the invoice and any claim raised from it.
 */
class StoreBuyerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Buyer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'gstin' => ['nullable', 'string', new Gstin],
            'type' => ['required', Rule::enum(BuyerType::class)],
            'treds_onboarded' => ['required', Rule::enum(TredsOnboarding::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'gstin' => filled($this->input('gstin'))
                ? strtoupper(trim((string) $this->input('gstin')))
                : null,
        ]);
    }
}
