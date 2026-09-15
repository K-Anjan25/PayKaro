<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Tests\TestCase;

/**
 * `.env.example` is the file every deployment starts from — CI's own "Prepare the
 * app" step is `cp -n .env.example .env` — and **none of the suite reads it**.
 *
 * That is how a copy change took CI down: an unquoted value containing a space
 * (`PAYKARO_HEADLINE=Make every invoice count`) is not valid dotenv, so the whole
 * file fails to parse and the application cannot boot at all. Every other test
 * passed, because they all ran with the developer's own `.env`.
 *
 * So this test parses the committed file with the same parser Laravel boots with,
 * and checks the documented defaults actually are the defaults.
 */
final class EnvExampleTest extends TestCase
{
    /**
     * @return array<string, string|null>
     */
    private function parsed(): array
    {
        $path = base_path('.env.example');

        $this->assertFileExists($path);

        // Array-backed: this reads the file exactly as booting would, without
        // writing any of it into the running environment.
        return Dotenv::createArrayBacked(base_path(), '.env.example')->load();
    }

    public function test_the_committed_example_parses(): void
    {
        $parsed = $this->parsed();

        // A parse failure throws before this line, which is the point: the failure
        // message names the offending value.
        $this->assertNotEmpty($parsed);
        $this->assertArrayHasKey('APP_KEY', $parsed, 'the example no longer documents APP_KEY');
    }

    public function test_every_key_the_app_reads_is_documented(): void
    {
        $parsed = $this->parsed();

        // The business rules the app reads with env(), plus the brand keys. A rule
        // nobody documented is a rule nobody knows they can change.
        $keys = [
            'PAYKARO_HEADLINE',
            'PAYKARO_DESCRIPTOR',
            'PAYKARO_MSME_DUE_DAYS',
            'PAYKARO_DEFAULT_TAX_RATE',
            'PAYKARO_BANK_RATE',
            'PAYKARO_INTEREST_MULTIPLIER',
            'PAYKARO_FINANCE_READY_SCORE',
            'PAYKARO_ALERT_LIMIT',
            'PAYKARO_DEMO',
        ];

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, $parsed, "{$key} is read by the app but missing from .env.example");
            $this->assertNotSame('', $parsed[$key], "{$key} is documented with no value");
        }
    }

    public function test_the_documented_brand_copy_is_the_shipped_brand_copy(): void
    {
        $parsed = $this->parsed();

        /** @var array<string, mixed> $defaults */
        $defaults = require config_path('paykaro.php');

        // Read the config file directly rather than `config()`: this is asserting
        // what the *file* defaults to, and the running environment would answer
        // with whatever .env happens to say.
        $this->assertSame(
            $defaults['headline'],
            $parsed['PAYKARO_HEADLINE'],
            'the headline in .env.example has drifted from config/paykaro.php',
        );

        $this->assertSame(
            $defaults['descriptor'],
            $parsed['PAYKARO_DESCRIPTOR'],
            'the descriptor in .env.example has drifted from config/paykaro.php',
        );
    }
}
