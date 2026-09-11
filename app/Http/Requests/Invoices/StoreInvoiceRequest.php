<?php

namespace App\Http\Requests\Invoices;

use App\Models\Buyer;
use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Raising an invoice.
 *
 * The buyer rule is the isolation check that matters: `exists:buyers,id` alone
 * would happily attach another business's buyer to this invoice. `where()`
 * against the resolved tenant closes that. (It is a Query Builder rule, so the
 * Eloquent tenant scope is not in play — hence the explicit business_id.)
 */
class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'number' => [
                'required', 'string', 'max:60',
                Rule::unique('invoices', 'number')->where('business_id', tenant_id()),
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
            'buyer_id.exists' => 'Choose one of your own buyers.',
            'invoice_date.date_format' => 'The invoice date must be a real date.',
            'base_amount.min' => 'The base amount must be greater than zero.',
            'number.unique' => 'You already have an invoice with that number.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // An empty tax field means "work it out", not "zero".
        if ($this->input('tax_amount') === '') {
            $this->merge(['tax_amount' => null]);
        }
    }
}
