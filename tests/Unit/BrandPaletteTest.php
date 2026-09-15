<?php

namespace Tests\Unit;

use App\Brand\Palette;
use PHPUnit\Framework\TestCase;

/**
 * The palette audit, as a test (BRAND_PLAN §3.4, §5.2).
 *
 * `Palette` states what each colour pairing is *for* and the ratio it has to
 * clear; this fails when a token change drops one below it. That is the whole
 * mechanism — without it, "the muted token looks fine" is the only review a
 * contrast change ever gets, and the four pairs it found were all shipping.
 */
final class BrandPaletteTest extends TestCase
{
    public function test_the_tokens_can_be_read_at_all(): void
    {
        // A silent parse failure would make every assertion below vacuous, which is
        // the failure mode of every "pinned value" test that reads a file.
        $this->assertGreaterThan(20, count(Palette::tokens(Palette::LIGHT)));
        $this->assertGreaterThan(20, count(Palette::tokens(Palette::DARK)));
        $this->assertSame('#0b132b', Palette::tokens(Palette::LIGHT)['--n-ink']);
    }

    public function test_every_declared_pair_clears_its_ratio_in_both_themes(): void
    {
        $failures = [];

        foreach (Palette::audit() as $row) {
            if ($row['missing']) {
                $failures[] = sprintf(
                    '%s: %s or %s is not a hex token, so it cannot be measured (%s)',
                    $row['theme'], $row['fg'], $row['bg'], $row['use'],
                );

                continue;
            }

            if (! $row['passes']) {
                $failures[] = sprintf(
                    '%s: %s on %s is %.2f:1, below %.1f — %s',
                    $row['theme'], $row['fg'], $row['bg'], $row['ratio'], $row['min'], $row['use'],
                );
            }
        }

        $this->assertSame([], $failures, "Contrast failures:\n  ".implode("\n  ", $failures));
    }

    public function test_the_dark_theme_restates_every_colour_token(): void
    {
        // A token `html.dark` forgets keeps its light value on an obsidian surface.
        $this->assertSame(
            [],
            Palette::undarkened(),
            'These tokens are declared in :root but never restated in html.dark, so they '
            .'keep their light value on a dark surface:',
        );
    }

    public function test_the_ratio_maths_matches_the_wcag_definition(): void
    {
        // The two anchors from the specification: black on white is 21:1, and a
        // colour against itself is 1:1.
        $this->assertEqualsWithDelta(21.0, Palette::contrast('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, Palette::contrast('#1d4ed8', '#1d4ed8'), 0.001);

        // …and a worked pair, so a refactor of the luminance function is caught
        // rather than silently re-tuning what "passing" means.
        $this->assertEqualsWithDelta(6.70, Palette::contrast('#1d4ed8', '#ffffff'), 0.01);
    }

    public function test_the_four_fixed_tokens_are_the_ones_that_were_failing(): void
    {
        // Each of these was measured below its floor before the audit existed. They
        // are pinned by name because they are the reason it exists: a future
        // "tidier" palette that restores the originals has to delete this test.
        $light = Palette::tokens(Palette::LIGHT);
        $dark = Palette::tokens(Palette::DARK);

        $this->assertSame('#5c6b81', $light['--n-ink-mute'], 'the muted token on the inset well');
        $this->assertSame('#15803d', $light['--n-success'], 'the KPI delta figure');
        $this->assertSame('#b45309', $light['--n-warning'], 'the due-date countdown');
        $this->assertSame('#0590dc', $light['--n-gold'], 'the TReDS tag on the dark utility bar');

        // The dark theme already cleared every pair; it is stated here so the two
        // themes cannot drift into disagreeing about what "muted" is.
        $this->assertSame('#94a3b8', $dark['--n-ink-mute']);
        $this->assertSame('#34d399', $dark['--n-success']);
    }
}
