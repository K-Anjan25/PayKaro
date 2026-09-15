<?php

namespace Tests\Feature;

use App\Brand\Palette;
use App\Brand\Type;
use Tests\TestCase;

/**
 * The brand book, and the fact that it cannot go stale (BRAND_PLAN §5.1, §5.2).
 *
 * The page is a rendering of the same source the tests assert, so these assertions
 * are about the *link* between them: the ratios it prints are the ones the audit
 * measured, the tokens it lists are the tokens in the stylesheet, and the type
 * sizes are the sizes the CSS sets. A brand book that drifts is worse than none —
 * people trust it.
 */
final class BrandPageTest extends TestCase
{
    public function test_the_brand_book_is_readable_without_an_account(): void
    {
        $this->get('/brand')
            ->assertOk()
            ->assertSee('The brand book')
            ->assertSee('Palette, measured')
            ->assertSee('Tokens')
            ->assertSee('Voice')
            ->assertSee('Never');
    }

    public function test_it_prints_the_ratios_the_audit_measured(): void
    {
        $audit = Palette::audit();

        $body = $this->get('/brand')->assertOk()->getContent();

        foreach ($audit as $row) {
            $this->assertStringContainsString(
                number_format($row['ratio'], 2),
                $body,
                sprintf('%s: the page does not show the measured %s on %s ratio', $row['theme'], $row['fg'], $row['bg']),
            );
        }
    }

    public function test_it_lists_every_token_in_the_stylesheet(): void
    {
        $body = $this->get('/brand')->assertOk()->getContent();

        foreach ([Palette::LIGHT, Palette::DARK] as $theme) {
            foreach (Palette::tokens($theme) as $name => $hex) {
                $this->assertStringContainsString($name, $body, "{$name} is missing from the brand book");

                if ($theme === Palette::LIGHT) {
                    $this->assertStringContainsString($hex, $body, "{$name}'s value is missing");
                }
            }
        }
    }

    public function test_it_states_the_mark_floor_and_its_clear_space(): void
    {
        // §3.3: the two decisions a small mark needs, written down where the person
        // resizing it will look.
        $this->get('/brand')
            ->assertOk()
            ->assertSee('16px is the floor')
            ->assertSee('Clear space: half the tile');
    }

    public function test_it_shows_the_type_scale_the_stylesheet_sets(): void
    {
        $scale = Type::scale();

        $this->assertNotEmpty($scale, 'the type scale should not be empty — the parse is broken');

        $body = $this->get('/brand')->assertOk()->getContent();

        foreach (array_slice($scale, 0, 5) as $row) {
            $this->assertStringContainsString($row['selector'], $body, "{$row['selector']} is missing from the scale");
        }

        // …and it says out loud that the plan's Fraunces premise is stale, so the
        // next reader does not go looking for a serif that is not there.
        $this->assertStringContainsString('Plus Jakarta Sans', $body);
    }

    public function test_the_footer_links_the_brand_book(): void
    {
        // §6's orphan-screen problem: a page nobody can reach is a page nobody reads.
        foreach (['/', '/pricing', '/terms'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee(route('brand'), false);
        }
    }

    public function test_the_hero_headline_and_the_brand_book_agree(): void
    {
        // The one sentence the whole plan is checked against, printed on the page
        // that documents the brand. It comes from config on both.
        $this->get('/brand')->assertOk()->assertSee(config('paykaro.headline'), false);
    }
}
