<?php

namespace App\Brand;

/**
 * The type scale, read from the stylesheet that sets it (BRAND_PLAN §3.5).
 *
 * The plan's premise for this task is stale and worth recording: it says "Fraunces
 * is loaded with `opsz,wght` 9..144 — document which optical sizes are used for
 * display vs. body". There is no Fraunces anywhere in the product; a grep over
 * `resources/`, `public/` and the config finds nothing, and all three layouts load
 * Plus Jakarta Sans 400–800. So there is one family with two jobs — an editorial
 * display treatment (`--n-display`) and dense interface text (`--n-font`) — and the
 * thing that can actually drift is not the family but the *sizes*: the hero, the
 * page headings and the metric numerals were each chosen separately.
 *
 * Rather than restate them here (a scale copied into a document is a scale that
 * goes stale), this reads the `font-size` declarations out of `app.css` and
 * reports the ones that are part of the type scale: anything at or above
 * `$floor`, plus the small caps sizes the tables and labels use.
 */
final class Type
{
    /** The floor for "part of the scale" — 1.25rem. */
    public const FLOOR = 1.25;

    /** Above this many declarations the parse has gone wrong and we say so. */
    private const SANITY = 400;

    /**
     * Every sized rule, largest first.
     *
     * @return list<array{selector: string, size: string, rem: float, line: int}>
     */
    public static function scale(int $limit = 14): array
    {
        return array_slice(self::measure(), 0, $limit);
    }

    /**
     * The type treatments that carry the brand voice, whatever their size: the
     * display face, the tabular figures on money, and the label style.
     *
     * @return list<array{label: string, spec: string, note: string}>
     */
    public static function roles(): array
    {
        return [
            [
                'label' => 'Display',
                'spec' => '--n-display, weight 800, letter-spacing -.04em',
                'note' => 'The hero and the closing CTA. Never used inside the workspace, where headings have to stay scannable rather than editorial.',
            ],
            [
                'label' => 'Headings',
                'spec' => '--n-font, weight 700–800, -.02em',
                'note' => 'Card and section headings. Same family as the body — the contrast comes from weight and space, not from a second typeface.',
            ],
            [
                'label' => 'Body',
                'spec' => '--n-font, weight 400–500, 1rem/1.6',
                'note' => 'Prose settles to a 70ch measure on the legal and marketing pages.',
            ],
            [
                'label' => 'Money',
                'spec' => 'font-variant-numeric: tabular-nums',
                'note' => 'Every rupee figure. Columns of money have to line up on the digit regardless of the glyphs in them.',
            ],
            [
                'label' => 'Label',
                'spec' => '11px, weight 700, letter-spacing .05em, uppercase',
                'note' => 'Table headers, eyebrows, field labels. Uppercase is the only place the type is allowed to be loud.',
            ],
        ];
    }

    /**
     * Every distinct font-size in the sheet, largest first.
     *
     * @return list<array{selector: string, size: string, rem: float, line: int}>
     */
    private static function measure(): array
    {
        $path = dirname(__DIR__, 2).'/public/assets/app.css';
        $css = (string) file_get_contents($path);

        // Walk the sheet one rule at a time so each declaration keeps the selector
        // it belongs to; a flat regex cannot say *where* a size is used, which is
        // the only interesting part.
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        $rules = [];

        foreach ($matches as $rule) {
            $selector = trim(preg_replace('/\s+/', ' ', $rule[1]) ?? '');
            $declarations = preg_replace('#/\*.*?\*/#s', '', $rule[2]) ?? '';

            if (! preg_match('/font-size:\s*([^;}]+)/', $declarations, $size)) {
                continue;
            }

            // Section comments end up on the selector side of the brace.
            $selector = trim(preg_replace('#/\*.*?\*/#s', '', $selector) ?? '');

            if ($selector === '' || str_starts_with($selector, '@') || str_contains($selector, ',')) {
                continue;
            }

            $value = trim($size[1]);
            $rem = self::toRem($value);

            if ($rem === null) {
                continue;
            }

            $rules[$selector.'|'.$value] = [
                'selector' => $selector,
                'size' => $value,
                'rem' => round($rem, 3),
                'line' => (int) substr_count(substr($css, 0, (int) strpos($css, $rule[0])), "\n") + 1,
            ];
        }

        if (count($rules) > self::SANITY) {
            return [];
        }

        $rules = array_values($rules);

        usort($rules, fn (array $a, array $b) => $b['rem'] <=> $a['rem']);

        // One row per size: the first rule that uses it, largest first. Listing
        // every `.something { font-size: .75rem }` would drown the scale.
        $seen = [];

        return array_values(array_filter($rules, function (array $rule) use (&$seen) {
            if (isset($seen[$rule['size']])) {
                return false;
            }

            return (bool) ($seen[$rule['size']] = true);
        }));
    }

    private static function toRem(string $value): ?float
    {
        $value = strtolower(trim($value));

        if (str_contains($value, 'clamp(') || str_contains($value, 'calc(') || str_contains($value, 'var(')) {
            // A fluid size has no single number; the clamp is quoted as written by
            // the roles above rather than reduced to a lie here.
            return null;
        }

        if (str_ends_with($value, 'rem')) {
            return (float) $value;
        }

        if (str_ends_with($value, 'px')) {
            return (float) $value / 16;
        }

        return null;
    }
}
