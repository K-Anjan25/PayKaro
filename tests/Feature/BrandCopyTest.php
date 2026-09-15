<?php

namespace Tests\Feature;

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
