/**
 * PayKaro — Node HTTP bridge for the agent sandbox.
 *
 * Runs the real Laravel app inside `@php-wasm/node` (PHP 8.3 compiled to
 * WebAssembly) and puts an HTTP server in front of it, for the environments
 * that have Node but no `php` binary and no Packagist access. `php artisan serve`
 * is still the normal way to run this app; this is the sandbox's way.
 *
 * Why a bridge at all: `php.wasm` exposes a *request* runner — script path,
 * relative URI, method, headers, body — and hands back the response the way a
 * web SAPI would (`httpStatusCode`, headers, body, cookies as `Set-Cookie`).
 * So the app runs unmodified: `public/index.php` is the script, and Laravel does
 * not know or care that its server is JavaScript.
 *
 * Three things this file is careful about:
 *
 *   - **APP_URL per request.** The Blade layouts link assets with `asset()`,
 *     which builds absolute URLs from APP_URL. A preview is served from a
 *     sandbox host, not from localhost, so a fixed APP_URL would make every
 *     stylesheet and image 404 in the browser. The request's own host (the
 *     `X-Forwarded-Host` the proxy sets, else `Host`) is passed down as
 *     `$_SERVER['APP_URL']`, which Laravel's env() reads *before* `.env` — so
 *     `asset()`, `route()` and redirects all come out pointing at the host the
 *     visitor actually used.
 *
 *   - **One request at a time.** Requests are serialized on a promise chain
 *     (the same shape the flat-PHP bridge used). Each `php.run()` is a fresh PHP
 *     process with reset superglobals, and everything that must survive between
 *     requests does so on the host filesystem: the SQLite database, the session
 *     table, the compiled Blade views.
 *
 *   - **Sessions that survive a POST.** Laravel's file session driver reads
 *     through `Filesystem::sharedGet()`, which takes a *shared* lock
 *     (`flock($handle, LOCK_SH)`) and returns an empty string when it cannot.
 *     PHP compiled to WebAssembly has no working advisory locks — the call comes
 *     back `false` — so under php-wasm every request loads an empty session,
 *     rotates the CSRF token, and the next form submit answers `419 Page
 *     Expired`. `prepare()` points `.env` at the database driver instead; the
 *     `sessions` table is already part of the app's own migrations, and the
 *     payload then lives in the SQLite file like everything else. See
 *     `ensureSandboxSessionDriver()`.
 *
 * usage:  cd bridge && npm install && VITEST=1 node serve.mjs
 * env:    HOST (default 0.0.0.0) · PORT (default 8080) · ROOT (default ..)
 *         PAYKARO_SKIP_SEED=1 to boot without `migrate --seed`
 */

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { bootstrap } from './bootstrap.mjs';
import { ensureCertificateAuthority } from './sandbox-ca.mjs';
/*
 * This @php-wasm/node build only allocates a process id for the runtime when
 * VITEST is set (see its `dangerousDefaultProcessIdAllocator`), and it reads that
 * env var while the module is being evaluated — so it cannot be set from a module
 * body that imports the runtime statically. Setting it here and pulling the
 * runtime in with `await import()` afterwards keeps that quirk inside this file
 * instead of making every caller remember `VITEST=1 node bridge/serve.mjs`.
 */
process.env.VITEST ??= '1';

const { PHP } = await import('@php-wasm/universal');
const { loadNodeRuntime, useHostFilesystem } = await import('@php-wasm/node');


const __dirname = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(process.env.ROOT || path.join(__dirname, '..'));
const PUBLIC = path.join(ROOT, 'public');
const FRONT_CONTROLLER = path.join(PUBLIC, 'index.php');

const HOST = process.env.HOST || '0.0.0.0';
const PORT = Number(process.env.PORT || 8080);

/* ------------------------------------------------------------------ */
/* PHP runtime                                                        */
/* ------------------------------------------------------------------ */

let phpPromise = null;
let chain = Promise.resolve();

function loadPhp() {
	phpPromise ??= (async () => {
		const id = await loadNodeRuntime('8.3');
		const php = new PHP(id);
		useHostFilesystem(php);
		php.chdir(ROOT);
		return php;
	})();
	return phpPromise;
}

/**
 * Run one request through the front controller, serialized.
 */
function runRequest({ method, uri, headers, body }) {
	const origin = headers['x-forwarded-origin'] || `http://localhost:${PORT}`;

	const job = chain.then(async () => {
		const php = await loadPhp();
		const serverVars = {
				/*
				 * The SAPI variables, stated explicitly.
				 *
				 * Running a script directly (rather than through a document root)
				 * leaves php-wasm reporting the request path as SCRIPT_NAME /
				 * PHP_SELF / SCRIPT_FILENAME with no DOCUMENT_ROOT — `GET /login`
				 * looks like a script called `/login`. Symfony derives the base
				 * path from exactly those values, so it decides every URL is
				 * `/login/...` and strips the path info down to nothing: routing
				 * silently falls back to `/` and `asset()` emits
				 * `/login/assets/app.css`. Naming the front controller is what a
				 * real web server does here (`SCRIPT_NAME=/index.php` with
				 * `REQUEST_URI=/login`), and Symfony then resolves the base path
				 * to '' — clean URLs, correct routing.
				 */
				SCRIPT_NAME: '/index.php',
				PHP_SELF: '/index.php',
				SCRIPT_FILENAME: FRONT_CONTROLLER,
				DOCUMENT_ROOT: PUBLIC,
				PATH_INFO: '',
				REQUEST_URI: uri,
				SERVER_PROTOCOL: 'HTTP/1.1',
				// Laravel's env() prefers $_SERVER over .env, and .env is immutable
				// once loaded — which is what lets the preview host win over the
				// APP_URL in .env.
				APP_URL: origin,
			};

		/*
		 * HTTPS is only set when the request really is TLS, and never to "off":
		 * php-wasm decides with `HTTPS = (provided || port === 443) ? "on" : "off"`,
		 * so *any* value — "off" included — means on. Omitting the key is what
		 * leaves a plain-HTTP request plain, and the SAPI's own default then applies.
		 */
		if (origin.startsWith('https://')) {
			serverVars.HTTPS = 'on';
		}

		const result = await php.run({
			scriptPath: FRONT_CONTROLLER,
			relativeUri: uri,
			protocol: 'HTTP/1.1',
			method,
			headers,
			body,
			$_SERVER: serverVars,
		});

		return result;
	});

	chain = job.then(
		() => {},
		() => {},
	);

	return job;
}

/* ------------------------------------------------------------------ */
/* Static files                                                       */
/* ------------------------------------------------------------------ */

const TYPES = {
	'.css': 'text/css; charset=utf-8',
	'.js': 'text/javascript; charset=utf-8',
	'.json': 'application/json; charset=utf-8',
	'.map': 'application/json; charset=utf-8',
	'.png': 'image/png',
	'.jpg': 'image/jpeg',
	'.jpeg': 'image/jpeg',
	'.webp': 'image/webp',
	'.svg': 'image/svg+xml',
	'.ico': 'image/x-icon',
	'.woff': 'font/woff',
	'.woff2': 'font/woff2',
	'.txt': 'text/plain; charset=utf-8',
	'.xml': 'application/xml; charset=utf-8',
	'.pdf': 'application/pdf',
};

/**
 * A path under public/ that exists on disk and is not PHP — the built-in server
 * serves those itself, so the bridge does too. Anything else goes to Laravel.
 */
function staticFile(pathname) {
	const rel = decodeURIComponent(pathname).replace(/^\/+/, '');
	if (rel === '' || rel.includes('\0')) return null;

	const file = path.join(PUBLIC, rel);
	if (!file.startsWith(PUBLIC + path.sep) || !fs.existsSync(file)) return null;

	const stat = fs.statSync(file);
	if (!stat.isFile() || file.endsWith('.php')) return null;

	return { file, type: TYPES[path.extname(file).toLowerCase()] || 'application/octet-stream' };
}

function serveFile(res, { file, type }) {
	const data = fs.readFileSync(file);
	res.statusCode = 200;
	res.setHeader('Content-Type', type);
	res.setHeader('Content-Length', data.length);
	res.setHeader('Cache-Control', 'no-cache');
	res.end(data);
}

/* ------------------------------------------------------------------ */
/* HTTP server                                                        */
/* ------------------------------------------------------------------ */

function headerObject(nodeHeaders) {
	const out = {};
	for (const [name, value] of Object.entries(nodeHeaders)) {
		if (value === undefined) continue;
		out[name] = Array.isArray(value) ? value.join(', ') : String(value);
	}
	return out;
}

function readBody(req) {
	return new Promise((resolve) => {
		const chunks = [];
		let size = 0;
		req.on('data', (chunk) => {
			size += chunk.length;
			if (size > 2_000_000) {
				req.destroy();
				return;
			}
			chunks.push(chunk);
		});
		req.on('end', () => resolve(Buffer.concat(chunks)));
		req.on('error', () => resolve(Buffer.alloc(0)));
	});
}

const server = http.createServer(async (req, res) => {
	/*
	 * Browsers abort requests constantly — a reload, a cancelled navigation, a
	 * closing iframe — and a socket that dies mid-response emits on the response
	 * rather than throwing here. Without a listener that is an unhandled 'error'
	 * event, which ends the process: the preview would go dark because a visitor
	 * pressed Escape. Swallow both sides; the connection is gone anyway.
	 */
	req.on('error', () => {});
	res.on('error', () => {});

	try {
		const url = new URL(req.url || '/', 'http://internal');
		const headers = headerObject(req.headers);

		// What the visitor's browser actually used — the sandbox proxy forwards
		// it. Falls back to Host, so a local run behaves like `artisan serve`.
		const host = headers['x-forwarded-host'] || headers.host || `localhost:${PORT}`;
		const proto = headers['x-forwarded-proto'] || 'http';

		// Present the request as the browser made it, the way a reverse proxy
		// does (`proxy_set_header Host $host`): Laravel builds absolute URLs,
		// cookies and redirects from this, so a preview on a sandbox host must
		// not be told it is 127.0.0.1.
		headers.host = host;
		headers['x-forwarded-origin'] = `${proto}://${host}`;

		const asset = staticFile(url.pathname);
		if (asset) {
			serveFile(res, asset);
			return;
		}

		const body = ['GET', 'HEAD'].includes(req.method || 'GET') ? undefined : await readBody(req);

		const result = await runRequest({
			method: req.method || 'GET',
			uri: url.pathname + url.search,
			headers,
			body,
		});

		res.statusCode = result.httpStatusCode || 200;

		for (const [name, values] of Object.entries(result.headers || {})) {
			if (name.toLowerCase() === 'content-length') continue; // Node recomputes it
			res.setHeader(name, values);
		}

		// The preview runs inside an iframe on the sandbox's domain.
		res.setHeader('Content-Security-Policy', "frame-ancestors * 'self'");

		if (result.errors) {
			console.error('[php]', String(result.errors).slice(0, 800));
		}

		const bytes = Buffer.from(result.bytes || []);
		res.setHeader('Content-Length', bytes.length);
		res.end(bytes);
	} catch (error) {
		console.error('[bridge]', error && error.stack ? error.stack : error);
		res.statusCode = 500;
		res.setHeader('Content-Type', 'text/plain; charset=utf-8');
		res.end('Bridge error: ' + ((error && error.message) || String(error)));
	}
});

/**
 * A malformed request (bad HTTP framing, half-open socket) must not take the
 * server down with it — the preview is one process, so it answers 400 and moves on.
 */
server.on('clientError', (error, socket) => {
	if (error.code === 'ECONNRESET' || !socket.writable) {
		socket.destroy();
		return;
	}

	socket.end('HTTP/1.1 400 Bad Request\r\nConnection: close\r\n\r\n');
});

/* ------------------------------------------------------------------ */
/* Boot: seed if we have to, then listen                              */
/* ------------------------------------------------------------------ */

/**
 * Everything `composer run setup` does, done in the order it does it, so a fresh
 * clone serves a working workspace. `migrate --seed` is idempotent (see
 * DemoWorkspaceSeeder), and the key is only generated when `.env` has none —
 * rotating it on every boot would invalidate every existing session.
 */
async function prepare() {
	const envPath = path.join(ROOT, '.env');
	const example = path.join(ROOT, '.env.example');

	if (!fs.existsSync(envPath) && fs.existsSync(example)) {
		fs.copyFileSync(example, envPath);
		console.log('[bridge] .env created from .env.example');
	}

	if (needsApplicationKey(envPath)) {
		await artisan(['key:generate']);
		console.log('[bridge] APP_KEY generated');
	}

	if (ensureSandboxSessionDriver(envPath)) {
		console.log('[bridge] SESSION_DRIVER=database — php-wasm cannot take the shared file lock file sessions read through');
	}

	if (process.env.PAYKARO_SKIP_SEED === '1') {
		console.log('[bridge] PAYKARO_SKIP_SEED=1 — not touching the database');
		return;
	}

	await artisan(['migrate', '--seed', '--force']);
}

/**
 * Point `.env` at a session driver that works under php-wasm.
 *
 * The file driver reads with `Filesystem::sharedGet()`, i.e. `fopen` + `flock($fh,
 * LOCK_SH)`; when the lock is refused it returns `''` — an empty session — no matter
 * how healthy the session file is. Emscripten has no advisory locks, so every request
 * would start an empty session, mint a new CSRF token and reject the next POST with
 * `419 Page Expired`. The database driver keeps the same payload in the app's own
 * `sessions` table, needs no locking, and is written to the same SQLite file the rest
 * of the demo data lives in.
 *
 * This is a sandbox accommodation, not a project default: `php artisan serve` on real
 * PHP keeps `SESSION_DRIVER=file` (and so does the test suite, which passes either way).
 *
 * @return bool whether `.env` had to change
 */
function ensureSandboxSessionDriver(envPath) {
	const desired = 'SESSION_DRIVER=database';

	if (!fs.existsSync(envPath)) return false;

	const env = fs.readFileSync(envPath, 'utf8');
	const next = /^SESSION_DRIVER=.*$/m.test(env)
		? env.replace(/^SESSION_DRIVER=.*$/m, desired)
		: `${env.trimEnd()}\n${desired}\n`;

	if (next === env) return false;

	fs.writeFileSync(envPath, next);

	return true;
}

function needsApplicationKey(envPath) {
	if (!fs.existsSync(envPath)) return true;

	const match = /^APP_KEY=(.*)$/m.exec(fs.readFileSync(envPath, 'utf8'));

	// `.env.example` ships `APP_KEY=` empty; anything set (base64:… or otherwise)
	// is a key this app has already been running with.
	return !match || match[1].trim() === '';
}

/**
 * One artisan command, in its own PHP process, with the argv the shim reads.
 */
async function artisan(args) {
	const php = await loadPhp();
	const result = await php.run({
		scriptPath: path.join(__dirname, 'php', 'artisan.php'),
		relativeUri: '/',
		method: 'GET',
		$_SERVER: {
			APP_URL: `http://localhost:${PORT}`,
			PAYKARO_ARGV: JSON.stringify(args),
		},
	});

	const output = (result.text || '').trim();
	if (output) console.log(output);
	if (result.errors) console.error('[artisan]', String(result.errors).slice(0, 800));
}

/*
 * Last line of defence. A preview that exits on one bad request is worse than a
 * preview that logs it, and php-wasm reports failures by rejecting promises deep
 * inside a request — surviving those is the difference between "one page 500s"
 * and "the sandbox has no preview any more".
 */
process.on('unhandledRejection', (reason) => {
	console.error('[bridge] unhandled rejection (still serving):', reason && reason.stack ? reason.stack : reason);
});

process.on('uncaughtException', (error) => {
	console.error('[bridge] uncaught exception (still serving):', error && error.stack ? error.stack : error);
});

await ensureCertificateAuthority();

// Without Composer there is no vendor/, and without vendor/ there is no app.
// bootstrap.mjs is a no-op when it is already installed.
await bootstrap();

// `.env`, an application key, and a seeded SQLite file — a fresh clone serves a
// working workspace; a warm one costs a re-check of a table that already exists.
await prepare();

server.listen(PORT, HOST, () => {
	console.log(`PayKaro bridge listening on http://${HOST}:${PORT} (root: ${ROOT})`);
});
