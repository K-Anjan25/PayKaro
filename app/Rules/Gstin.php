<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\InvokableRule;

/**
 * GSTIN — the 15-character registration number an invoice and any claim raised
 * from it are filed under.
 *
 * Validated rather than stored as typed: a wrong GSTIN on a buyer record is not
 * a typo, it is a claim that will not stand.
 */
class Gstin implements InvokableRule
{
    public const PATTERN = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/';

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  callable(string): void  $fail
     */
    public function __invoke($attribute, $value, $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, self::normalize($value))) {
            $fail('The :attribute must be a valid GSTIN — 2 digits, 5 letters, 4 digits, then a check character (e.g. 36AAACS1234F1Z5).');
        }
    }

    public static function normalize(string $value): string
    {
        return strtoupper(trim($value));
    }
}
