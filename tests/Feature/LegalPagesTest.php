<?php

namespace Tests\Feature;

use App\Support\Legal;
use Illuminate\Support\Str;
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

    public static function documents(): array
    {
        return [
            'terms' => ['/terms', 'Terms of use'],
            'privacy' => ['/privacy', 'Privacy'],
            'security' => ['/security', 'Security'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('documents')]
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
        // The honest list, not just the flattering one.
        $security->assertSee('does NOT give you', escape: false)->assertSee('no content security policy', escape: false);
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
        \Illuminate\Support\Facades\Route::get('/__legal-escaping-probe', function () {
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
                $this->assertLessThan(400, strlen($section['body']), $doc.': unnaturally long block');

                foreach ($section['list'] ?? [] as $item) {
                    $this->assertNotEmpty(trim($item), $doc.': empty list item');
                }
            }
        }
    }

    public function test_the_landing_and_app_footers_link_the_legal_set(): void
    {
        $landing = $this->get('/')->assertOk();

        foreach (['/terms', '/privacy', '/security'] as $uri) {
            $landing->assertSee('href="'.$uri.'"', escape: false);
        }

        // Signed-in users get the same documents from inside the workspace.
        $this->workspace();
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('href="'.route('privacy').'"', escape: false)
            ->assertSee('href="'.route('security').'"', escape: false);
    }
}
