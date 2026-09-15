<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * One promise, one key.
 *
 * BRAND_PLAN §1.1: the product stated its value proposition five different ways —
 * a config `tagline` that actually held a *descriptor*, the real headline
 * hard-coded in the landing hero and again in the `<title>` default, the
 * descriptor re-typed as a literal in the footer, and two more wordings in the
 * auth shell and an article CTA.
 *
 * The split is `paykaro.headline` (the brand line) and `paykaro.descriptor` (the
 * line under the wordmark). These tests hold it together the only way a copy
 * promise can be held: by overriding the config and checking the *rendered page*
 * follows. A literal left anywhere fails here, which a "does the page contain the
 * default string" assertion would happily pass.
 */
final class BrandCopyTest extends TestCase
{
    /**
     * Pages that state the brand line, with the selector-free text they must carry.
     *
     * @return array<string, string>
     */
    private function headlinePages(): array
    {
        return [
            'landing hero' => '/',
            'sign-in shell' => '/login',
            'pricing footer' => '/pricing',
        ];
    }

    public function test_the_headline_reads_the_same_on_every_page_that_states_it(): void
    {
        $headline = config('paykaro.headline');

        $this->assertNotEmpty($headline);

        foreach ($this->headlinePages() as $where => $uri) {
            $this->get($uri)->assertOk()->assertSee($headline, false);
        }
    }

    public function test_the_headline_comes_from_config_and_not_from_a_literal(): void
    {
        config(['paykaro.headline' => 'Every rupee, on the record']);

        foreach ($this->headlinePages() as $where => $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('Every rupee, on the record', false)
                ->assertDontSee('Make every invoice count');
        }

        // …and so does the page title, which is where a second hard-coded copy
        // lived before the split.
        $this->get('/')->assertOk()->assertSee('<title>PayKaro — Every rupee, on the record</title>', false);
    }

    public function test_no_view_retypes_the_brand_line_or_the_descriptor(): void
    {
        // §1.1's whole failure mode: a string that is *almost* centralised. The rule
        // is therefore enforced at the source — the copy may only exist in
        // config/paykaro.php, so a view that retypes it fails here rather than
        // waiting for someone to change the key and notice one page did not follow.
        $literals = ['Make every invoice count', 'MSME invoice & receivables tracker'];

        $offenders = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            $contents = File::get($view->getPathname());

            // Templates render copy from config; comments and docs are allowed to
            // quote it (the brand book explains the rule, which means naming it).
            if (preg_match('#\{\{--.*?--\}\}#s', $contents, $comments)) {
                $contents = str_replace($comments[0], '', $contents);
            }

            foreach ($literals as $literal) {
                if (str_contains($contents, $literal)) {
                    $offenders[] = str_replace(resource_path('views').'/', '', $view->getPathname()).' → '.$literal;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Copy that belongs to config('paykaro.*') is retyped in:\n  ".implode("\n  ", $offenders),
        );
    }

    public function test_the_title_and_the_share_card_state_the_same_brand_line(): void
    {
        // Repetition is not the failure — a page may state the line in its copy, its
        // <title> and its share card, and all three had better be the same string.
        // (The first version of this test asserted "once per page", which the
        // landing page fails four times over and legitimately: title, og:title,
        // closing CTA, footer.)
        $headline = config('paykaro.headline');

        $body = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<title>'.config('app.name').' — '.$headline.'</title>', $body);
        $this->assertStringContainsString('<meta property="og:title" content="'.config('app.name').' — '.$headline.'">', $body);

        // A named page leads with its own name and ends with the brand line.
        $pricing = $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Pricing — '.config('app.name').'</title>', $pricing);
        $this->assertStringContainsString('<meta property="og:title" content="Pricing — '.config('app.name').'">', $pricing);
    }

    public function test_the_descriptor_comes_from_config_and_not_from_a_literal(): void
    {
        config(['paykaro.descriptor' => 'Receivables, evidenced']);

        // Beside the wordmark on the marketing and auth shells, and in the landing
        // footer, which used to re-type the string.
        foreach (['/', '/login'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertSee('Receivables, evidenced')
                ->assertDontSee('MSME invoice &amp; receivables tracker');
        }
    }

    public function test_the_old_tagline_key_is_gone_rather_than_aliased(): void
    {
        // An alias would keep two keys holding one value, which is how the drift
        // started. A deployment that still sets PAYKARO_TAGLINE has to be renamed
        // — see the note in config/paykaro.php.
        $this->assertNull(config('paykaro.tagline'));
    }

    public function test_the_hero_still_sets_a_short_headline(): void
    {
        // The hero splits the headline to give its last two words the italic and
        // the accent. A one- or two-word headline has no such pair, and must render
        // as plain text rather than losing a word to an empty <em>.
        config(['paykaro.headline' => 'Invoice count']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Invoice count')
            ->assertDontSee('<em></em>', false);
    }
}
