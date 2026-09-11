<?php

namespace App\Http\Requests\Invoices;

use App\Enums\EvidenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Ticking one line of the evidence checklist.
 *
 * The legacy router accepted *any* `type` string and silently ignored unknowns;
 * here the enum is the whitelist, so a typo in a new form fails loudly instead
 * of vanishing.
 */
class UpdateEvidenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('toggleEvidence', $this->invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(EvidenceType::class)],
            'present' => ['required', 'boolean'],
        ];
    }

    public function type(): EvidenceType
    {
        return EvidenceType::from($this->string('type')->value());
    }

    public function present(): bool
    {
        return $this->boolean('present');
    }
}
