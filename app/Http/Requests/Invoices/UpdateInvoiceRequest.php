<?php

namespace App\Http\Requests\Invoices;

use App\Models\Buyer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Editing a raised invoice.
 *
 * The invoice number is unique *per business* here, not globally: two suppliers
 * both raising INV-2026-001 is normal, and it must not look like a collision.
 */
class UpdateInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $invoice = $this->invoice;

        return [
            'number' => [
                'required',
                'string',
                'max:60',
                Rule::unique('invoices', 'number')
                    ->where('business_id', tenant_id())
                    ->ignore($invoice),
            ],
            'buyer_id' => [
                'required',
                'integer',
                Rule::exists(Buyer::class, 'id')->where('business_id', tenant_id()),
            ],
            'invoice_date' => ['required', 'date_format:Y-m-d'],
            'base_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999'],
            'tax_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'number.unique' => 'You already have an invoice with that number.',
            'buyer_id.exists' => 'Choose one of your own buyers.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('tax_amount') === '') {
            $this->merge(['tax_amount' => null]);
        }
    }
}
