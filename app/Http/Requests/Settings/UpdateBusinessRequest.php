<?php

namespace App\Http\Requests\Settings;

use App\Rules\Gstin;
use App\Rules\Ifsc;
use App\Rules\Pan;
use App\Rules\UdyamRegistration;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Business identity and bank details.
 *
 * Owner-only (see BusinessPolicy): these are the fields a payment is made
 * against and the ones an MSEFC notice is addressed to.
 */
class UpdateBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->user()?->business);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'gstin' => ['nullable', 'string', new Gstin],
            'pan' => ['nullable', 'string', new Pan],
            'udyam_no' => ['nullable', 'string', new UdyamRegistration],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_acc_no' => ['nullable', 'string', 'max:32'],
            'bank_ifsc' => ['nullable', 'string', new Ifsc],
            'treds_registered' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $upper = ['gstin', 'pan', 'udyam_no', 'bank_ifsc'];

        foreach ($upper as $field) {
            if (filled($this->input($field))) {
                $this->merge([$field => strtoupper(trim((string) $this->input($field)))]);
            }
        }

        foreach (['gstin', 'pan', 'udyam_no', 'bank_name', 'bank_acc_no', 'bank_ifsc'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }
}
