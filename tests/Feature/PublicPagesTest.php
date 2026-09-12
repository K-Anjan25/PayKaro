<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Invoice;
use App\Support\News;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * The public half of the app, and the URLs the flat-PHP build left behind.
 *
 * Marketing pages must render signed-out (they are what convinces a supplier
 * before they have an account), and the old query-string addresses must keep
 * resolving because they live in emails and WhatsApp threads already sent.
 */
final class PublicPagesTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    public function test_the_landing_page_sells_the_pipeline_without_an_account(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Make every')
            ->assertSee('Raised → accepted → financed → settled')
            ->assertSee('TReDS-ready finance queue')
            ->assertSee('MSME invoice & receivables tracker');
    }

    public function test_the_landing_page_links_the_news_it_advertises(): void
    {
        foreach (News::all() as $article) {
            $this->get('/')->assertOk()->assertSee('/news/'.$article->slug);
        }
    }

    public function test_pricing_shows_the_tiers_and_marks_the_recommended_one(): void
    {
        $this->get('/pricing')
            ->assertOk()
            ->assertSee('Starter')
            ->assertSee('Pro')
            ->assertSee('Enterprise')
            ->assertSee('₹1,499')
            ->assertSee('Most popular')
            ->assertSee('is-hot');
    }

    public function test_the_help_and_contact_pages_are_public(): void
    {
        $this->get('/help')->assertOk();
        $this->get('/contact')->assertOk();
    }

    public function test_a_news_article_renders(): void
    {
        $article = News::all()[0];

        $this->get('/news/'.$article->slug)
            ->assertOk()
            ->assertSee($article->title);
    }

    public function test_an_unknown_news_slug_lands_on_the_news_section(): void
    {
        $this->get('/news/not-a-real-story')
            ->assertRedirect('/#news');
    }

    public function test_the_news_index_jumps_to_the_landing_section(): void
    {
        $this->get('/news')->assertRedirect('/#news');
    }

    public function test_the_legal_aliases_still_point_at_help(): void
    {
        foreach (['/terms', '/privacy', '/security'] as $path) {
            $this->get($path)->assertRedirect('/help');
        }
    }

    public function test_the_workspace_links_are_offered_to_guests_but_guarded(): void
    {
        $this->get('/')->assertOk()->assertSee('Sign in');

        foreach (['/dashboard', '/invoices', '/treds'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_legacy_invoice_urls_redirect_to_the_restful_ones(): void
    {
        $user = $this->workspace();
        $buyer = Buyer::factory()->create();
        $invoice = Invoice::factory()->forBuyer($buyer)->create();

        $this->get('/invoice?id='.$invoice->id)
            ->assertRedirect(route('invoices.show', $invoice));

        $this->get('/invoice?edit='.$invoice->id)
            ->assertRedirect(route('invoices.edit', $invoice));

        $this->get('/claim?id='.$invoice->id)
            ->assertRedirect(route('invoices.claim', $invoice));

        $this->get('/invoices/new')->assertRedirect('/invoices/create');
    }

    public function test_a_legacy_url_without_an_id_goes_to_the_list(): void
    {
        $this->workspace();

        $this->get('/invoice')->assertRedirect(route('invoices.index'));
        $this->get('/claim')->assertRedirect(route('invoices.index'));
    }

    public function test_legacy_urls_are_still_behind_the_login_for_guests(): void
    {
        $this->get('/invoice?id=1')->assertRedirect(route('login'));
        $this->get('/invoices/new')->assertRedirect(route('login'));
    }

    public function test_an_unknown_url_is_a_page_not_found(): void
    {
        $this->get('/definitely-not-here')->assertNotFound();
    }

    public function test_the_health_endpoint_answers_without_a_session(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_the_stylesheet_is_shipped_with_the_app(): void
    {
        $this->assertFileExists(public_path('assets/app.css'));
        $this->assertStringContainsString(
            '.pkg-timeline',
            file_get_contents(public_path('assets/app.css')),
        );
    }
}
