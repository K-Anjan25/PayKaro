<?php

namespace Tests\Feature;

use App\Brand\Palette;
use App\Brand\Type;
use Illuminate\Support\Facades\File;
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

    public function test_it_documents_the_surface_and_elevation_scale(): void
    {
        // §3.6: the primitives a new screen has to copy. Each one is read from the
        // stylesheet, so the page cannot describe a radius the product does not use.
        $body = $this->get('/brand')->assertOk()->getContent();

        foreach (Palette::surfaces() as $surface) {
            $this->assertStringContainsString($surface['token'], $body, "{$surface['label']} is missing from the brand book");
            $this->assertStringContainsString($surface['use'], $body, "{$surface['label']} does not say what it is for");
        }

        // The four elevation levels and the corner are the load-bearing ones.
        foreach (['--n-radius', '--n-shadow', '--n-shadow-2', '--n-shadow-3', '--n-inset'] as $token) {
            $this->assertStringContainsString($token, $body);
        }

        $this->assertStringContainsString('The accent bars', $body);
        $this->assertStringContainsString('Three surfaces, in order', $body);
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

    public function test_views_take_their_colour_from_tokens(): void
    {
        // §5.2's last piece. A hex in a template is a colour that no longer follows
        // the theme, the dark palette or the contrast audit — it is invisible to all
        // three. Only three files may hold one, and each says why.
        $allowlist = [
            'mail/' => 'an inbox has no CSS custom properties, so the mail layer must repeat the palette',
            'partials/brand-meta.blade.php' => 'theme-color is read by the browser, which cannot resolve var()',
            'marketing/brand.blade.php' => 'previews the committed icon assets, whose colours are fixed by the PNGs',
            'components/auth/google-button.blade.php' => "Google's sign-in branding guidelines mandate the mark's own four colours",
        ];

        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            $relative = str_replace(resource_path('views').'/', '', $view->getPathname());

            foreach ($allowlist as $allowed => $reason) {
                if (str_starts_with($relative, $allowed)) {
                    continue 2;
                }
            }

            $contents = preg_replace('#\{\{--.*?--\}\}#s', '', File::get($view->getPathname())) ?? '';

            if (preg_match('/#[0-9a-fA-F]{6}\b/', $contents, $match)) {
                $offenders[] = $relative.' → '.$match[0];
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Colour that belongs in a token:\n  ".implode("\n  ", $offenders)
            ."\nUse var(--n-*), or add the file to the allowlist in this test with a reason.",
        );
    }

    public function test_the_allowlist_has_not_rotted(): void
    {
        // An allowlist entry for a file that no longer has a hex is an excuse nobody
        // is using, and it hides the next violation in that file.
        $allowed = [
            'mail/layout.blade.php',
            'partials/brand-meta.blade.php',
            'marketing/brand.blade.php',
            'components/auth/google-button.blade.php',
        ];

        foreach ($allowed as $file) {
            $contents = File::get(resource_path('views/'.$file));

            $this->assertMatchesRegularExpression(
                '/#[0-9a-fA-F]{6}\b/',
                $contents,
                "{$file} no longer needs to be on the colour allowlist — drop it.",
            );
        }
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
