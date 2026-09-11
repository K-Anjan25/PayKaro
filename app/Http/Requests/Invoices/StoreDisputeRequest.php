<?php

namespace App\Http\Requests\Invoices;

use App\Enums\DisputeForum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Filing a delayed-payment claim.
 *
 * The legacy code clamped an unknown forum to `msefc` in silence; a workspace
 * filing to the wrong forum needs an error, not a guess.
 */
class StoreDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('dispute', $this->invoice);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'forum' => ['required', Rule::enum(DisputeForum::class)],
        ];
    }

    public function forum(): DisputeForum
    {
        return DisputeForum::from($this->string('forum')->value());
    }
}
