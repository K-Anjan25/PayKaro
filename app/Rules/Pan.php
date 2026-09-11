<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\InvokableRule;

/**
 * Permanent Account Number for a business (ABCDD1234E).
 */
class Pan implements InvokableRule
{
    public const PATTERN = '/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/';

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  callable(string): void  $fail
     */
    public function __invoke($attribute, $value, $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, strtoupper(trim($value)))) {
            $fail('The :attribute must be a PAN — 5 letters, 4 digits, 1 letter (e.g. AAACS1234F).');
        }
    }
}
