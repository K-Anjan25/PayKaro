<?php

namespace Tests\Feature;

use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * The legal set: three real documents behind the footer links, plus the
 * two guarantees that make them trustworthy rather than decorative — every
 * section has an anchor you can link to, and the prose is never rendered as HTML.
 */
final class LegalPagesTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public static function documents(): array
    {
        return [
            'terms' => ['/terms', 'Terms of use'],
            'privacy' => ['/privacy', 'Privacy'],
            'security' => ['/security', 'Security'],
        ];
    }

    #[DataProvider('documents')]
    public function test_each_document_renders(string $uri, string $title): void
    {
        $this->get($uri)
            ->assertOk()
            ->assertSee($title)
            ->assertSee('Last revised');
    }

    public function test_the_three_documents_cover_the_gaps_a_visitor_asks_about(): void
    {
        $terms = $this->get('/terms')->assertOk();
        $privacy = $this->get('/privacy')->assertOk();
        $security = $this->get('/security')->assertOk();

        // What the tool is *not* — the single most load-bearing sentence here.
        $terms->assertSee('not a payment gateway');
        // No analytics/beacons, and the outbound request that does exist.
        $privacy->assertSee('Google Fonts', escape: false);
        // The honest list, not just the flattering one. Matched without its leading
        // word: the gap is named in a bullet now ("No content security policy…"),
        // and the assertion is about the gap being documented, not about its case.
        $security->assertSee('does NOT give you', escape: false)->assertSee('content security policy', escape: false);
        $security->assertSee('composer audit');
    }

    public function test_sections_are_anchored_for_linking(): void
    {
        foreach (['terms', 'privacy', 'security'] as $doc) {
            $sections = Legal::$doc()['sections'];
            $response = $this->get('/'.$doc)->assertOk();

            foreach ($sections as $section) {
                $slug = Str::slug($section['heading']);

                $response->assertSee('id="'.$slug.'"', escape: false);
                $response->assertSee('href="#'.$slug.'"', escape: false);
            }
        }
    }

    public function test_legal_copy_is_escaped_not_rendered(): void
    {
        // Feed the layout deliberately unsafe content: if any of its four props
        // ever rendered raw, this returns a live element instead of text.
        Route::get('/__legal-escaping-probe', function () {
            return view('components.legal-layout', [
                'title' => 'Probe <img src=x onerror=alert(1)>',
                'kicker' => '<script>alert(1)</script>',
                'lede' => '<b>bold?</b>',
                'sections' => [[
                    'heading' => '<svg onload=alert(1)>',
                    'body' => 'Text with & and \'quotes\'.',
                ]],
            ]);
        });

        $response = $this->get('/__legal-escaping-probe')->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', escape: false);
        $response->assertDontSee('<img src=x', escape: false);
        $response->assertDontSee('<svg onload', escape: false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false)
            ->assertSee('Text with &amp; and &#039;quotes&#039;.', escape: false);
    }

    public function test_the_docs_carry_no_empty_sections_and_no_placeholders(): void
    {
        foreach (['terms', 'privacy', 'security'] as $doc) {
            $sections = Legal::$doc()['sections'];

            $this->assertNotEmpty($sections, $doc.' has no sections');

            foreach ($sections as $section) {
                $this->assertArrayHasKey('heading', $section);
                $this->assertNotEmpty(trim($section['body']), $doc.': empty body under "'.$section['heading'].'"');

                // Cap a *paragraph*, not a section. At section level this asserted
                // the length of every body in the file — 16 of 20 exceeded 400 — so
                // it measured the copy's total size rather than anything about prose,
                // and it fought a legitimate two-paragraph section. The unit that can
                // actually read as a wall is the paragraph, and 520 sits just above
                // the longest one here: a guard against unbounded growth, not a
                // house style. Tighten it by splitting a block, never by raising it.
                foreach (preg_split('/\n\n+/', $section['body']) as $paragraph) {
                    $this->assertLessThan(520, strlen(trim($paragraph)), $doc.': unnaturally long paragraph under "'.$section['heading'].'"');
                }

                foreach ($section['list'] ?? [] as $item) {
                    $this->assertNotEmpty(trim($item), $doc.': empty list item');
                }
            }
        }
    }

    public function test_the_landing_and_app_footers_link_the_legal_set(): void
    {
        $landing = $this->linkedPaths($this->get('/')->assertOk());

        foreach (['/terms', '/privacy', '/security'] as $uri) {
            $this->assertContains($uri, $landing, 'the landing footer should link '.$uri);
        }

        // Signed-in users get the same documents from inside the workspace.
        $this->workspace();
        $workspace = $this->linkedPaths($this->get('/dashboard')->assertOk());

        foreach (['/terms', '/privacy', '/security'] as $uri) {
            $this->assertContains($uri, $workspace, 'the workspace footer should link '.$uri);
        }
    }

    /**
     * The paths a page links to — so the assertion is about *where* a link goes,
     * not how it is spelled. Both footers build their hrefs with `route()`, which
     * makes them absolute and prefixes the test's APP_URL; pinning the literal
     * `href="/terms"` asserted the URL's shape rather than the link's existence.
     *
     * @return array<int, string>
     */
    private function linkedPaths(TestResponse $response): array
    {
        preg_match_all('/href="([^"]+)"/', $response->getContent(), $matches);

        return array_values(array_unique(array_map(
            fn (string $href): string => parse_url($href, PHP_URL_PATH) ?: '/',
            $matches[1],
        )));
    }
}
