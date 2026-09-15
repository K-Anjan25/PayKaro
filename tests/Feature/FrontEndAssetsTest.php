<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The front end ships as committed static files, and this is the test that says so.
 *
 * `WIREFRAME_AUDIT.md` §4 raised the design package's Play CDN as a blocker: all 63
 * wireframe exports load `https://cdn.tailwindcss.com` and build their styles in the
 * browser. Pasting them in would have put a compiler in front of every page load.
 *
 * The option that shipped is the hybrid — the design's tokens and type scale ported
 * into the `--n-*` custom properties and the 165 component classes in
 * `public/assets/app.css`, rebuilt in the existing Blade components. No build step:
 * `SPEC.md` promises one, and this keeps it a promise rather than a memory.
 *
 * What that means in practice is narrow enough to assert: the only third-party
 * request a page may make is the font, and nothing may load a script that styles the
 * page at runtime.
 */
final class FrontEndAssetsTest extends TestCase
{
    /** The one external host allowed on a page: Plus Jakarta Sans. */
    private const ALLOWED_REMOTE = ['fonts.googleapis.com', 'fonts.gstatic.com'];

    public function test_the_stylesheet_is_a_committed_asset(): void
    {
        $stylesheet = public_path('assets/app.css');

        $this->assertFileExists($stylesheet);

        // A stylesheet that has been emptied or truncated is a page with no styling
        // and a green test suite, which is the failure this asserts against.
        $this->assertGreaterThan(50_000, File::size($stylesheet), 'app.css is too small to be the real stylesheet');
        $this->assertStringContainsString('--n-canvas', File::get($stylesheet));
    }

    public function test_no_page_loads_a_script_that_builds_styles_in_the_browser(): void
    {
        // The specific thing §4 warned about, asserted by name: Play CDN, or any
        // other runtime CSS generator, on a page a member is meant to use.
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            $contents = File::get($view->getPathname());

            foreach (['cdn.tailwindcss.com', 'unpkg.com', 'jsdelivr.net', 'tailwind'] as $needle) {
                if (str_contains($contents, $needle)) {
                    $offenders[] = str_replace(resource_path('views').'/', '', $view->getPathname()).' → '.$needle;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A view is loading a CDN build tool:\n  ".implode("\n  ", $offenders)
            ."\nPort the styles into public/assets/app.css instead — the repo ships no build step.",
        );
    }

    public function test_the_only_remote_request_is_the_font(): void
    {
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            $contents = File::get($view->getPathname());

            preg_match_all('#https://([a-z0-9.-]+)#i', $contents, $matches);

            foreach (array_unique($matches[1]) as $host) {
                // Documentation URLs and schema.org are text, not requests.
                if (in_array($host, [...self::ALLOWED_REMOTE, 'www.w3.org', 'schema.org'], true)) {
                    continue;
                }

                $offenders[] = str_replace(resource_path('views').'/', '', $view->getPathname()).' → '.$host;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A view is fetching something from a host we do not control:\n  ".implode("\n  ", $offenders)
            ."\nSelf-host it, or add the host here with the reason.",
        );
    }

    public function test_every_layout_loads_the_same_stylesheet_and_the_same_font(): void
    {
        // Three layouts, one visual identity. A layout that loads its own font stack
        // or its own stylesheet is how a product ends up looking like two products.
        foreach (['app', 'auth', 'public'] as $layout) {
            $contents = File::get(resource_path("views/components/layouts/{$layout}.blade.php"));

            $this->assertStringContainsString("asset('assets/app.css')", $contents, "{$layout} does not load app.css");
            $this->assertStringContainsString('fonts.googleapis.com/css2?family=Plus+Jakarta+Sans', $contents, "{$layout} does not load Plus Jakarta Sans");
        }
    }

    public function test_every_asset_the_head_names_is_committed_and_not_empty(): void
    {
        // `favicon.ico` was a 0-byte file for the whole of the previous branch: a
        // browser asked for it on every page and got nothing, and no test noticed,
        // because no test asked for a file size. So this reads the tags the head
        // actually emits and checks each file behind them.
        $head = File::get(resource_path('views/partials/brand-meta.blade.php'));

        preg_match_all("/asset\('([^']+)'\)/", $head, $matches);

        $this->assertNotEmpty($matches[1], 'the head names no assets at all');

        foreach (array_unique($matches[1]) as $asset) {
            $path = public_path($asset);

            $this->assertFileExists($path, "the head names {$asset}, which is not in public/");
            $this->assertGreaterThan(0, File::size($path), "{$asset} is empty");

            if ($asset === 'favicon.ico') {
                // A 0-byte ICO and a 1-byte ICO are both "present"; this one is a real
                // multi-size icon, and it is what a browser shows in a tab.
                $this->assertGreaterThan(1000, File::size($path), 'favicon.ico is not a real icon file');
            }
        }
    }
}
