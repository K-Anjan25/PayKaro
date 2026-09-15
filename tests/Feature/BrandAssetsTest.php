<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesWorkspace;
use Tests\TestCase;

/**
 * The brand assets that are files, and the tags that point at them.
 *
 * Two of BRAND_PLAN's gap-list items were invisible to every other test because
 * nothing rendered them: `public/favicon.ico` was a **0-byte file** (every browser
 * tab blank), and no layout carried `og:` tags at all, so a PayKaro link pasted
 * into WhatsApp rendered with no title, description or image. Both are copies of
 * the same mistake — an asset everyone assumes exists — so both are asserted here:
 * the file is real and the right size, and each layout points at it.
 *
 * The PNG dimensions come out of the IHDR chunk rather than `getimagesize()`, so
 * the assertion does not quietly disappear on a build without GD.
 */
final class BrandAssetsTest extends TestCase
{
    use CreatesWorkspace;
    use RefreshDatabase;

    /**
     * @return array{0: int, 1: int}
     */
    private function pngSize(string $path): array
    {
        $bytes = file_get_contents($path);

        $this->assertNotFalse($bytes, "{$path} could not be read");
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $bytes, "{$path} is not a PNG");

        // IHDR is always the first chunk: 8 bytes signature, 4 length, 4 type.
        $header = unpack('Nwidth/Nheight', substr($bytes, 16, 8));

        return [(int) $header['width'], (int) $header['height']];
    }

    public function test_the_favicon_is_a_real_multi_size_icon(): void
    {
        $path = public_path('favicon.ico');

        $this->assertFileExists($path);
        // It shipped as 0 bytes for the life of the port: present in the tree,
        // blank in every tab, and nothing failed.
        $this->assertGreaterThan(1024, filesize($path), 'favicon.ico is empty or a stub');
        $this->assertStringStartsWith("\x00\x00\x01\x00", file_get_contents($path), 'favicon.ico is not an ICO');
    }

    public function test_the_share_card_is_the_size_every_crawler_expects(): void
    {
        [$width, $height] = $this->pngSize(public_path('assets/img/og-default.png'));

        // 1.91:1 is what every platform renders without cropping it.
        $this->assertSame([1200, 630], [$width, $height]);
    }

    public function test_the_touch_and_tab_icons_are_present_and_sized(): void
    {
        foreach ([[32, 'icon-32.png'], [180, 'icon-180.png'], [512, 'icon-512.png']] as [$size, $file]) {
            $path = public_path('assets/img/'.$file);

            $this->assertFileExists($path);
            $this->assertSame([$size, $size], $this->pngSize($path), "{$file} is the wrong size");
        }
    }

    public function test_every_layout_links_the_icon_set(): void
    {
        // The public pages are visited signed out: `/login` bounces an
        // authenticated member to the workspace, so the guest view — the one that
        // actually ships — is what has to carry the icons.
        foreach (['landing' => '/', 'auth' => '/login', 'legal' => '/terms'] as $name => $uri) {
            $body = $this->get($uri)->assertOk()->getContent();

            $this->assertStringContainsString('rel="icon"', $body, "{$name} has no tab icon");
            $this->assertStringContainsString('favicon.ico', $body, "{$name} does not point at the favicon");
            $this->assertStringContainsString('apple-touch-icon', $body, "{$name} has no home-screen icon");
        }

        $this->workspace();
        $body = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('favicon.ico', $body, 'workspace does not point at the favicon');
        $this->assertStringContainsString('apple-touch-icon', $body, 'workspace has no home-screen icon');
    }

    public function test_the_shared_link_card_is_absolute_and_per_page(): void
    {
        foreach (['/', '/login', '/pricing'] as $uri) {
            $body = $this->get($uri)->assertOk()->getContent();

            foreach (['og:title', 'og:description', 'og:image', 'og:url', 'twitter:card'] as $tag) {
                $this->assertStringContainsString($tag, $body, "{$uri} is missing {$tag}");
            }

            // A crawler has no page to resolve a relative image against, so a
            // relative og:image is the same as having none.
            $this->assertMatchesRegularExpression(
                '#<meta property="og:image" content="https?://[^"]+/assets/img/og-default\.png">#',
                $body,
                "{$uri} has a relative og:image",
            );

            $this->assertStringContainsString('content="summary_large_image"', $body, "{$uri} does not ask for the big card");
        }

        $this->workspace();
        $workspace = $this->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('og:title', $workspace, 'the workspace is missing og:title');
        $this->assertMatchesRegularExpression(
            '#<meta property="og:image" content="https?://[^"]+/assets/img/og-default\.png">#',
            $workspace,
            'the workspace has a relative og:image',
        );
    }
}
