/**
 * PayKaro — run the test suite in the agent sandbox.
 *
 * `php artisan test` is the normal way, and CI runs it. It cannot run here: it
 * shells out to `vendor/bin/phpunit` through Symfony Process, and this PHP is
 * compiled to WebAssembly, where there are no child processes to spawn. So the
 * suite is started the way a caller would start PHPUnit directly — one
 * `php vendor/phpunit/phpunit/phpunit` invocation through php-wasm's real CLI
 * SAPI, which is where argv, exit codes and STDERR come from.
 *
 * Two things about this runtime are worth knowing before trusting a result:
 *
 *   - **`vendor/bin/` does not exist.** `bridge/bootstrap.mjs` writes a
 *     composer-compatible autoloader, not the Composer bin shims, so the
 *     launcher is named by its path. `vendor/bin/phpunit` is used when a real
 *     `composer install` has produced it.
 *
 *   - **Console output mocking is disabled.** `$this->artisan(...)` builds a
 *     `PendingCommand`, whose mocked `OutputStyle` traps php-wasm outright; set
 *     `PAYKARO_SANDBOX_PHPUNIT=1` (below) and `tests/TestCase.php` skips it, so
 *     `RefreshDatabase` — which migrates through that call — can run. CI does
 *     not set it and still exercises the mock, so a test that *asserts* on
 *     command output is a CI-only check.
 *
 * Everything else is the ordinary suite: `phpunit.xml` pins APP_ENV=testing,
 * in-memory SQLite and a fixed APP_KEY, so no `.env` and no seeded database are
 * needed.
 *
 * usage:  cd bridge && npm ci
 *         node test.mjs                             # whole suite
 *         node test.mjs --filter=WorkspacePagesTest  # any PHPUnit argument
 *         node test.mjs --testdox
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

/*
 * This @php-wasm/node build only allocates a process id for the runtime when
 * VITEST is set (see its `dangerousDefaultProcessIdAllocator`), and it reads that
 * env var while the module is being evaluated — so it cannot be set from a module
 * body that imports the runtime statically. Same reason as bridge/serve.mjs.
 */
process.env.VITEST ??= '1';

const { PHP } = await import('@php-wasm/universal');
const { loadNodeRuntime, useHostFilesystem } = await import('@php-wasm/node');

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(process.env.ROOT || path.join(__dirname, '..'));

const bin = path.join(ROOT, 'vendor', 'bin', 'phpunit');
const launcher = fs.existsSync(bin) ? bin : path.join(ROOT, 'vendor', 'phpunit', 'phpunit', 'phpunit');

if (!fs.existsSync(launcher)) {
	console.error(`[test] ${launcher} is missing — run composer install, or node bridge/bootstrap.mjs in the sandbox.`);
	process.exit(2);
}

const runtime = await loadNodeRuntime('8.3');
const php = new PHP(runtime);
useHostFilesystem(php);

const args = ['php', launcher, ...process.argv.slice(2)];

const response = await php.cli(args, {
	cwd: ROOT,
	env: {
		PAYKARO_SANDBOX_PHPUNIT: '1',
		// phpunit.xml sets these itself; passing them through keeps a caller's
		// `DB_DATABASE=… phpunit …` override working, exactly as on a laptop.
		...(process.env.DB_CONNECTION ? { DB_CONNECTION: process.env.DB_CONNECTION } : {}),
		...(process.env.DB_DATABASE ? { DB_DATABASE: process.env.DB_DATABASE } : {}),
	},
});

const decoder = new TextDecoder();
const pump = async (stream, sink) => {
	for await (const chunk of stream) sink.write(decoder.decode(chunk));
};

await Promise.all([pump(response.stdout, process.stdout), pump(response.stderr, process.stderr)]);

const exitCode = await response.exitCode;
if (exitCode !== 0) console.error(`[test] phpunit exited ${exitCode}`);

process.exit(exitCode === 0 ? 0 : 1);
