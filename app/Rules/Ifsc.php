<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\InvokableRule;

/**
 * Indian Financial System Code for a bank branch (HDFC0001234) — where settled
 * money is expected to land.
 */
class Ifsc implements InvokableRule
{
    public const PATTERN = '/^[A-Z]{4}0[A-Z0-9]{6}$/';

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  callable(string): void  $fail
     */
    public function __invoke($attribute, $value, $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, strtoupper(trim($value)))) {
            $fail('The :attribute must be an IFSC — 4 letters, a zero, then 6 characters (e.g. HDFC0001234).');
        }
    }
}
