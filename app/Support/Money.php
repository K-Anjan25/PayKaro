<?php

namespace App\Support;

/**
 * Indian-grouped rupee formatting (lakh / crore), not `number_format`'s
 * thousands grouping.
 *
 * Plain text by design: every call site renders it inside `{{ }}` or into the
 * printable claim packet, so it must never carry markup.
 */
final class Money
{
    /**
     * Render an amount as ₹ with lakh/crore grouping.
     *
     * Decimals are shown only when there are paise, which is what the invoice
     * tables and KPI cards are laid out for.
     */
    public static function inr(float|int|null|string $amount): string
    {
        return self::format($amount);
    }

    /**
     * Render with a fixed number of decimals (used by GST fields where an
     * amount of "₹1,00,000.00" must not collapse to "₹1,00,000").
     */
    public static function precise(float|int|null|string $amount, int $decimals = 2): string
    {
        return self::format($amount, $decimals);
    }

    /**
     * Short form for chart axes and other places with no room for full
     * grouping: 1.5L above a lakh, 25K above a thousand, plain rupees below.
     */
    public static function compact(float|int|null|string $amount): string
    {
        $value = is_numeric($amount) ? round((float) $amount) : 0.0;
        $symbol = self::symbol();

        if (abs($value) >= 100000) {
            $lakh = abs($value) / 100000;

            return $symbol.($value < 0 ? '-' : '').(fmod($lakh, 1.0) ? number_format($lakh, 1) : number_format($lakh, 0)).'L';
        }

        if (abs($value) >= 1000) {
            return $symbol.($value < 0 ? '-' : '').number_format(abs($value) / 1000, 0).'K';
        }

        return $symbol.($value < 0 ? '-' : '').number_format(abs($value), 0);
    }

    private static function format(float|int|null|string $amount, ?int $decimals = null): string
    {
        $value = is_numeric($amount) ? (float) $amount : 0.0;
        $sign = $value < 0 ? '-' : '';
        $value = abs($value);

        // Round once, at paisa precision, so display never disagrees with the
        // stored balance by a rounding step of its own.
        $rounded = round($value, 2);
        $whole = (int) floor($rounded);
        $paise = (int) round(($rounded - $whole) * 100);

        if ($paise >= 100) {
            $whole += 1;
            $paise -= 100;
        }

        $digits = (string) $whole;

        if (strlen($digits) > 3) {
            $lastThree = substr($digits, -3);
            $rest = substr($digits, 0, -3);

            if ($rest !== '') {
                $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            }

            $digits = $rest.','.$lastThree;
        }

        $formatted = $sign.self::symbol().$digits;

        $places = $decimals ?? ($paise > 0 ? 2 : 0);

        if ($places > 0) {
            // Two digits, always. `number_format($paise, 0)` renders 2 paise as "2",
            // so a claim packet printed "₹4,112.2" for ₹4,112.02 — a figure wrong by a
            // factor of ten, in a document whose whole job is to be added up by
            // someone else.
            $formatted .= '.'.str_pad((string) $paise, 2, '0', STR_PAD_LEFT);
        }

        return $formatted;
    }

    private static function symbol(): string
    {
        return (string) config('paykaro.currency.symbol', '₹');
    }
}
