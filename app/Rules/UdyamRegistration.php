<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\InvokableRule;

/**
 * Udyam registration number (UDYAM-TS-12-3456789) — the MSME certificate whose
 * presence is what makes the MSMED Act's due window and interest apply at all.
 */
class UdyamRegistration implements InvokableRule
{
    public const PATTERN = '/^UDYAM-[A-Z]{2}-[0-9]{2}-[0-9]{7}$/';

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     * @param  callable(string): void  $fail
     */
    public function __invoke($attribute, $value, $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, strtoupper(trim($value)))) {
            $fail('The :attribute must read UDYAM-XX-DD-NNNNNNN (e.g. UDYAM-TS-12-3456789).');
        }
    }
}
