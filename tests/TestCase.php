<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        /*
         * Running the suite in the agent sandbox (bridge/test.mjs) means running
         * it on PHP compiled to WebAssembly, and one piece of the framework does
         * not survive that runtime: Laravel's `PendingCommand` — what
         * `$this->artisan('...')` builds in a test — traps the wasm runtime with
         * `RuntimeError: unreachable` while constructing its mocked console
         * output. Everything around it is fine (the console kernel, migrations
         * and Mockery all work here); it is the mocked output that dies, and
         * `RefreshDatabase` migrates through exactly that call, so *every*
         * database-backed test would fail before its first assertion.
         *
         * Turning the console-output mock off routes `artisan()` through the
         * kernel, which is all `RefreshDatabase` needs. The bridge sets this
         * variable; CI never does, so on CI's real PHP the output is mocked and
         * `expectsOutput(...)` behaves as documented. A test that asserts on
         * artisan output needs CI to prove it — that is the trade the sandbox
         * makes, rather than every test being unrunnable here.
         */
        if (getenv('PAYKARO_SANDBOX_PHPUNIT') === '1') {
            $this->mockConsoleOutput = false;
        }

        parent::setUp();
    }
}
