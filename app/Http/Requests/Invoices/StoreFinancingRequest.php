<?php

namespace App\Http\Requests\Invoices;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * Recording a TReDS disbursal.
 *
 * The disbursal amount is optional and defaults to the invoice total in the
 * workflow, because the financier's offer is usually the whole paper.
 */
class StoreFinancingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('finance', $this->invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'financier' => ['nullable', 'string', 'max:120'],
            'discount_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'amount_disbursed' => ['nullable', 'numeric', 'min:0.01', 'max:999999999'],
            'disbursed_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['amount_disbursed', 'discount_rate'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }

        if (blank($this->input('disbursed_on'))) {
            $this->merge(['disbursed_on' => now()->toDateString()]);
        }
    }
}
