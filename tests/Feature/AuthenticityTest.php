<?php

namespace Tests\Feature;

use App\Support\News;
use App\Support\Proof;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The authenticity gate (BRAND_PLAN §4).
 *
 * The marketing pages were publishing traction the product cannot support:
 *
 *   ₹4.2Cr+  "Receivables tracked"          landing, twice — one with the sub-label
 *                                          "Across active tenants in the last 30 days"
 *   ₹240Cr+  "Invoices cleared"             the sign-in shell
 *   99.8%    "Reconciliation rate"          the sign-in shell
 *   <48 Hrs  "Disbursal speed"              the sign-in shell
 *   "Enterprise security · RBI regulated entities", "256-bit SSL / Encrypted ledger",
 *   "RBI TReDS / Direct gateway", "GSTN & TReDS Verified"
 *
 * None of it was computed by anything. The product's only data is its own seeded
 * demo book, `app/Support/Legal.php` is scrupulous about admitting what the product
 * does *not* do, and `Proof` exists so the published figures come from the config
 * and enums the domain computes with.
 *
 * These tests are the reason the replacements stay replaced: the banned list is
 * literal, so a re-introduced claim fails here rather than waiting for a reader to
 * notice.
 */
final class AuthenticityTest extends TestCase
{
    /**
     * Claims that were published and could not be checked by anyone.
     *
     * @return list<string>
     */
    private function retired(): array
    {
        return [
            '₹4.2Cr', '₹240Cr', '99.8%', '<48 Hrs', 'Across active tenants',
            // Vague or unearned trust language.
            'RBI regulated', 'Direct gateway', '256-bit SSL', 'Encrypted ledger',
            'GSTN &', 'TReDS Verified', 'Enterprise security',
        ];
    }

    public function test_the_marketing_pages_publish_no_unsupported_figures(): void
    {
        foreach (['/', '/login', '/signup', '/pricing', '/help', '/contact', '/terms', '/security'] as $uri) {
            $body = $this->get($uri)->assertOk()->getContent();

            foreach ($this->retired() as $claim) {
                $this->assertStringNotContainsString(
                    $claim,
                    $body,
                    "{$uri} publishes \"{$claim}\", which nothing in the product measures.",
                );
            }
        }
    }

    public function test_no_view_can_retype_a_retired_claim(): void
    {
        // The rendered-page test above only proves the strings are gone from the
        // pages that exist today. This one covers the template that gets written
        // tomorrow — and the comments that explain a removal are allowed to name
        // what was removed, so Blade comments and whole-line PHP comments are
        // stripped before the scan. (Inline `// …` after code is not stripped: a
        // claim hidden on the end of a line is exactly what this is looking for.)
        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            $contents = File::get($view->getPathname());
            $contents = preg_replace('#\{\{--.*?--\}\}#s', '', $contents) ?? '';

            $contents = implode("\n", array_filter(
                preg_split('/\R/', $contents) ?: [],
                fn (string $line) => ! preg_match('#^\s*(//|\*|/\*)#', $line),
            ));

            foreach ($this->retired() as $claim) {
                if (str_contains($contents, $claim)) {
                    $offenders[] = str_replace(resource_path('views').'/', '', $view->getPathname()).' → '.$claim;
                }
            }
        }

        $this->assertSame([], $offenders, "Unsupported claims are back in:\n  ".implode("\n  ", $offenders));
    }

    public function test_every_published_figure_follows_the_configuration(): void
    {
        // The strongest form of the rule: change the number the domain computes with
        // and the marketing page changes with it. A literal would not move.
        config([
            'paykaro.msme_due_days' => 30,
            'paykaro.interest_multiplier' => 4,
            'paykaro.finance_ready_score' => 77,
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('30 days')
            ->assertSee('4×')
            ->assertSee('77');

        $this->get('/login')
            ->assertOk()
            ->assertSee('30 days')
            ->assertSee('4×');
    }

    public function test_the_marketing_pages_state_what_they_do_not_claim(): void
    {
        // The counterpart to Legal's disclosures: a page that names the boundaries of
        // its own claims is the page that gets believed.
        $this->get('/')
            ->assertOk()
            ->assertSee('What we do not claim')
            ->assertSee('the demo workspace is seeded', false);
    }

    public function test_the_notes_from_the_team_are_labeled_as_notes(): void
    {
        // §4.2: three authored articles with press-release framing were presented as
        // a news feed. They are what they are.
        $this->get('/')
            ->assertOk()
            ->assertSee('Product notes')
            ->assertSee('Written by the PayKaro team')
            ->assertSee('These are our own notes, not a news wire');

        $article = News::all()[0];

        $this->get(route('news.show', $article->slug))
            ->assertOk()
            ->assertSee('Written by the PayKaro team');
    }

    public function test_proof_exposes_only_checkable_claims(): void
    {
        $claims = Proof::claims();

        $this->assertNotEmpty($claims);

        foreach ($claims as $claim) {
            $this->assertArrayHasKey('value', $claim);
            $this->assertArrayHasKey('label', $claim);
            $this->assertArrayHasKey('note', $claim);

            // A claim states a mechanism, a count or a threshold. Anything shaped
            // like a traction metric (a currency figure, or a percentage) is out of
            // scope for this class by construction.
            $this->assertStringNotContainsString('₹', $claim['value'], 'Proof must not carry money figures');
            $this->assertStringNotContainsString('%', (string) $claim['value'], 'Proof must not carry percentage claims');
        }

        // …and the numbers it states are the ones the config holds.
        $this->assertSame((string) config('paykaro.msme_due_days').' days', $claims[0]['value']);
        $this->assertSame((string) config('paykaro.interest_multiplier').'×', $claims[1]['value']);
    }
}
