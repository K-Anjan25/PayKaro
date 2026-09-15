<?php

namespace App\Brand;

/**
 * The palette as code (BRAND_PLAN §5.2).
 *
 * `config/paykaro.php` holds every number the domain computes with and
 * `Receivables` is the only reader; this is the same idea for colour. The tokens
 * live in one place — the `:root` and `html.dark` blocks of
 * `public/assets/app.css`, which is what actually paints the product — and this
 * class reads them, measures them and states what each pairing is *for*.
 *
 * The point is not the arithmetic. It is that "the muted token looks fine" stops
 * being the test, and `BrandPaletteTest` fails instead. It found four real
 * failures in the shipped palette, all of them small text on a surface nobody had
 * measured:
 *
 *   --n-ink-mute  4.34:1 on the inset well — every table header in the product
 *   --n-success   3.30:1 on card — the KPI delta figure beside every metric
 *   --n-warning   3.19:1 on card — the day-40 due-date countdown
 *   --n-gold      4.49:1 on the dark utility bar — the TReDS tag, by 0.01
 *
 * All four are now fixed at the token, and this file is why they stay fixed.
 */
final class Palette
{
    public const LIGHT = 'light';

    public const DARK = 'dark';

    /** WCAG 2.1 AA: body text. */
    public const TEXT = 4.5;

    /** WCAG 2.1 AA: large text (≥24px, or ≥18.66px bold) and UI boundaries. */
    public const LARGE = 3.0;

    /** @var array<string, array<string, string>>|null */
    private static ?array $cache = null;

    /**
     * The colour tokens of one theme, as `token name => hex`.
     *
     * Only hex values: a token declared in `rgba()` has no measured contrast, and
     * pretending otherwise would make the audit a lie.
     *
     * @return array<string, string>
     */
    public static function tokens(string $theme = self::LIGHT): array
    {
        return self::themes()[$theme] ?? [];
    }

    /**
     * Both themes, parsed once per request.
     *
     * @return array<string, array<string, string>>
     */
    public static function themes(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $css = (string) file_get_contents(self::stylesheet());

        self::$cache = [
            self::LIGHT => self::parseBlock($css, ':root'),
            self::DARK => self::parseBlock($css, 'html.dark'),
        ];

        return self::$cache;
    }

    /**
     * Every colour pairing the product relies on, with the ratio it has to clear.
     *
     * `use` is the reason the pair exists, and it is deliberately specific: a
     * threshold is only meaningful against a described usage, and the specific
     * usage is what tells the next person whether their new screen may use it.
     *
     * `fg` and `bg` are token names, literal hexes, or an `rgba()` wash — the
     * stylesheet is not tidy, and a palette that can only describe its own tokens
     * would describe less than the product does. `themes` narrows a pair that only
     * exists in one theme: `--n-paper` is white in light (fine on the obsidian
     * band) and obsidian in dark (invisible on it), and pretending otherwise is
     * how the dark theme ended up with white text on pale accents.
     *
     * @return list<array{fg: string, bg: string, min: float, use: string, themes?: list<string>}>
     */
    public static function pairs(): array
    {
        return [
            ['fg' => '--n-ink', 'bg' => '--n-paper', 'min' => self::TEXT, 'use' => 'body text on cards, sheets and modals'],
            ['fg' => '--n-ink', 'bg' => '--n-canvas', 'min' => self::TEXT, 'use' => 'body text on the page background'],
            ['fg' => '--n-ink', 'bg' => '--n-paper-2', 'min' => self::TEXT, 'use' => 'text inside an inset well or alert'],
            ['fg' => '--n-ink-soft', 'bg' => '--n-paper', 'min' => self::TEXT, 'use' => 'secondary body copy'],
            ['fg' => '--n-ink-soft', 'bg' => '--n-canvas', 'min' => self::TEXT, 'use' => 'secondary copy on the page'],
            ['fg' => '--n-ink-mute', 'bg' => '--n-paper', 'min' => self::TEXT, 'use' => 'metadata, captions, table cells'],
            ['fg' => '--n-ink-mute', 'bg' => '--n-canvas', 'min' => self::TEXT, 'use' => 'metadata on the page'],
            ['fg' => '--n-ink-mute', 'bg' => '--n-paper-2', 'min' => self::TEXT, 'use' => 'table headers and placeholders on the inset well'],
            ['fg' => '--n-blue', 'bg' => '--n-paper', 'min' => self::TEXT, 'use' => 'links and the active tab'],
            ['fg' => '--n-blue', 'bg' => '--n-canvas', 'min' => self::TEXT, 'use' => 'links outside a card'],
            ['fg' => '--n-blue', 'bg' => '--n-blue-soft', 'min' => self::TEXT, 'use' => 'text in a selected row or blue tint'],
            ['fg' => '--n-info', 'bg' => '--n-paper', 'min' => self::TEXT, 'use' => 'the accepted state, as text'],
            ['fg' => '--n-coral', 'bg' => '--n-paper', 'min' => self::TEXT, 'use' => 'overdue amounts and negative figures'],
            ['fg' => '--n-coral', 'bg' => '--n-canvas', 'min' => self::TEXT, 'use' => 'overdue amounts on the page'],
            ['fg' => '--n-success', 'bg' => '--n-paper', 'min' => self::TEXT, 'use' => 'the KPI delta and settled state, as text'],
            ['fg' => '--n-success', 'bg' => '--n-paper-2', 'min' => self::TEXT, 'use' => 'settled state inside a well'],
            ['fg' => '--n-warning', 'bg' => '--n-paper', 'min' => self::TEXT, 'use' => 'the due-date countdown, as text'],
            ['fg' => '--n-warning', 'bg' => '--n-paper-2', 'min' => self::TEXT, 'use' => 'countdown inside a well'],

            /* Text on a filled accent — buttons, active tabs, step bullets, the hot
               price card. One token owns it (`--n-on-accent`), because the accents
               are dark in the light theme and light in the dark one, and fifteen
               rules were hard-coding #fff: white on a pale sky button is 2.24:1. */
            ['fg' => '--n-on-accent', 'bg' => '--n-blue', 'min' => self::TEXT, 'use' => 'primary buttons, active tabs, step bullets'],
            ['fg' => '--n-on-accent', 'bg' => '--n-blue-strong', 'min' => self::TEXT, 'use' => 'a primary button on hover'],
            ['fg' => '--n-on-accent', 'bg' => '--n-gold-strong', 'min' => self::TEXT, 'use' => 'the liquidity button and checked boxes'],
            ['fg' => '--n-on-accent', 'bg' => '--n-coral', 'min' => self::TEXT, 'use' => 'the warning step bullet'],
            ['fg' => '--n-on-accent', 'bg' => '--n-info', 'min' => self::TEXT, 'use' => 'the accepted step bullet'],

            /* The dark band, which is a literal in the stylesheet rather than a
               token: the utility bar paints #0b1a2c and keeps it in both themes. */
            ['fg' => '--n-gold', 'bg' => '#0b1a2c', 'min' => self::TEXT, 'use' => 'the TReDS tag on the utility bar'],
            ['fg' => '#cfd9e7', 'bg' => '#0b1a2c', 'min' => self::TEXT, 'use' => 'the utility bar\'s own links'],
            ['fg' => 'rgba(255,255,255,.78)', 'bg' => '--n-blue-deep', 'min' => self::TEXT, 'use' => 'marketing footer copy on the obsidian band'],
            ['fg' => 'rgba(255,255,255,.6)', 'bg' => '--n-blue-deep', 'min' => self::TEXT, 'use' => 'the footer\'s bottom row on the obsidian band'],
            ['fg' => '--n-paper', 'bg' => '--n-blue-deep', 'min' => self::TEXT, 'use' => 'footer headings, which are literally white', 'themes' => [self::LIGHT]],

            /* Accents below body size are allowed to be accents. These are stated
               at the large-text floor on purpose — and only these. */
            ['fg' => '--n-gold', 'bg' => '--n-paper', 'min' => self::LARGE, 'use' => 'liquidity accent bars, dots and chips (never body copy)'],
            ['fg' => '--n-gold', 'bg' => '--n-canvas', 'min' => self::LARGE, 'use' => 'liquidity accent on the page (never body copy)'],
            ['fg' => '--n-coral', 'bg' => '--n-coral-soft', 'min' => self::LARGE, 'use' => 'alert icons and borders on their tint'],
        ];
    }

    /**
     * Every declared pair, measured, in both themes.
     *
     * @return list<array{theme: string, fg: string, bg: string, min: float, use: string, ratio: float, passes: bool, missing: bool}>
     */
    public static function audit(): array
    {
        $rows = [];

        foreach ([self::LIGHT, self::DARK] as $theme) {
            foreach (self::pairs() as $pair) {
                if (isset($pair['themes']) && ! in_array($theme, $pair['themes'], true)) {
                    continue;
                }

                $background = self::resolve($pair['bg'], $theme);
                $foreground = $background === null ? null : self::resolve($pair['fg'], $theme, $background);

                $missing = $background === null || $foreground === null;
                $ratio = $missing ? 0.0 : self::contrast($foreground, $background);

                $rows[] = [
                    'theme' => $theme,
                    'fg' => $pair['fg'],
                    'bg' => $pair['bg'],
                    'min' => $pair['min'],
                    'use' => $pair['use'],
                    'ratio' => round($ratio, 2),
                    'passes' => ! $missing && $ratio + 0.0001 >= $pair['min'],
                    'missing' => $missing,
                ];
            }
        }

        return $rows;
    }

    /**
     * Turn a colour declaration into an opaque hex, in one theme.
     *
     * Handles the three shapes the stylesheet uses for text: a token (`--n-ink`),
     * a literal (`#cfd9e7`, `#fff`), and a white wash over a surface
     * (`rgba(255,255,255,.78)`), which is composited over the background it sits on
     * because that is what the eye actually receives.
     */
    public static function resolve(string $colour, string $theme, ?string $over = null): ?string
    {
        $colour = trim($colour);

        if (str_starts_with($colour, '--')) {
            return self::tokens($theme)[$colour] ?? null;
        }

        if (str_starts_with($colour, '#')) {
            return self::expand($colour);
        }

        if (preg_match('/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/i', $colour, $m)) {
            if ($over === null) {
                return null;
            }

            $alpha = isset($m[4]) && $m[4] !== '' ? (float) $m[4] : 1.0;
            $base = [hexdec(substr($over, 1, 2)), hexdec(substr($over, 3, 2)), hexdec(substr($over, 5, 2))];

            $mixed = '';

            foreach ([0, 1, 2] as $i) {
                $mixed .= str_pad(dechex((int) round(((float) $m[$i + 1] * $alpha) + ($base[$i] * (1 - $alpha)))), 2, '0', STR_PAD_LEFT);
            }

            return '#'.$mixed;
        }

        return null;
    }

    private static function expand(string $hex): ?string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return preg_match('/^[0-9a-f]{6}$/i', $hex) ? '#'.strtolower($hex) : null;
    }

    /**
     * WCAG 2.1 relative-luminance contrast ratio between two hex colours.
     */
    public static function contrast(string $a, string $b): float
    {
        [$lighter, $darker] = [self::luminance($a), self::luminance($b)];

        if ($lighter < $darker) {
            [$lighter, $darker] = [$darker, $lighter];
        }

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        $channels = [];

        foreach ([0, 2, 4] as $offset) {
            $value = hexdec(substr($hex, $offset, 2)) / 255;

            $channels[] = $value <= 0.03928
                ? $value / 12.92
                : (($value + 0.055) / 1.055) ** 2.4;
        }

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Tokens that `html.dark` does not restate.
     *
     * A colour token the dark theme forgets is a token that keeps its light value
     * on an obsidian surface — most often invisible text, and nothing else warns
     * about it.
     *
     * @return list<string>
     */
    public static function undarkened(): array
    {
        $light = self::names(self::stylesheet(), ':root');
        $dark = self::names(self::stylesheet(), 'html.dark');

        return array_values(array_diff($light, $dark));
    }

    private static function stylesheet(): string
    {
        return dirname(__DIR__, 2).'/public/assets/app.css';
    }

    /**
     * @return array<string, string>
     */
    private static function parseBlock(string $css, string $selector): array
    {
        $body = preg_replace('#/\*.*?\*/#s', '', self::block($css, $selector)) ?? '';

        $tokens = [];

        foreach (explode(';', $body) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $declaration, 2);
            $name = trim($name);
            $value = trim($value);

            if (str_starts_with($name, '--') && preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                $tokens[$name] = $value;
            }
        }

        return $tokens;
    }

    /**
     * @return list<string>
     */
    private static function names(string $css, string $selector): array
    {
        $body = preg_replace('#/\*.*?\*/#s', '', self::block($css, $selector)) ?? '';

        preg_match_all('/(--[a-z0-9-]+)\s*:/i', $body, $matches);

        return array_values(array_unique($matches[1]));
    }

    private static function block(string $css, string $selector): string
    {
        $start = preg_match('/^'.preg_quote($selector, '/').'\s*\{/m', $css, $match, PREG_OFFSET_CAPTURE);

        if ($start !== 1) {
            return '';
        }

        // Start *after* the opening brace: including it hands the first declaration
        // a name like "html.dark {\n\t--n-canvas", which then parses as no token at
        // all — a silent hole in the palette, and one the assertions below would
        // have reported as "not a hex token" rather than as a parser bug.
        $start = $match[0][1] + strlen($match[0][0]);
        $i = $start;
        $depth = 1;

        while ($i < strlen($css) && $depth > 0) {
            $depth += match ($css[$i]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
            $i++;
        }

        // …and exclude the closing brace.
        return substr($css, $start, max(0, $i - $start - 1));
    }
}
