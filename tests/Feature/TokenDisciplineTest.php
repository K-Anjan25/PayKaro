<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Colour that belongs in a token, and an allowlist that has not rotted.
 *
 * These two guards moved here when the brand-book page was deleted; they were
 * never about that page. §5.2's rule stands on its own: a hex in a template is
 * a colour that no longer follows the theme, the dark palette or the contrast
 * audit — it is invisible to all three.
 */
final class TokenDisciplineTest extends TestCase
{
    public function test_views_take_their_colour_from_tokens(): void
    {
        $allowlist = [
            'mail/' => 'an inbox has no CSS custom properties, so the mail layer must repeat the palette',
            'partials/brand-meta.blade.php' => 'theme-color is read by the browser, which cannot resolve var()',
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
}
