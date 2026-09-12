/**
 * PayKaro — make this checkout runnable without Composer (sandbox helper).
 *
 * `composer install` is the normal way to populate `vendor/`, and the README says
 * so. This exists for the environments that have Node, no `php`, no `composer`
 * and no route to Packagist — the agent sandbox, where the live preview has to
 * come from somewhere. Every package is still the *locked* commit: the zipball
 * URL in `composer.lock` is the source, so nothing is re-resolved and no version
 * is chosen here.
 *
 * Three steps, each skipped when it is already done:
 *
 *   1. download every package in composer.lock (dist zipballs, in parallel) and
 *      unpack it to vendor/<vendor>/<name>       → skipped if vendor/autoload.php exists
 *   2. generate a composer-compatible autoloader (bridge/php/gen-autoload.php,
 *      run inside php-wasm) plus vendor/composer/installed.json, which Laravel
 *      reads for package discovery                    → skipped if already current
 *   3. leave the database alone — `serve.mjs` seeds it on boot
 *
 * usage:  node bridge/bootstrap.mjs            # idempotent; safe to re-run
 *         node bridge/bootstrap.mjs --force    # reinstall the lot
 * env:    ROOT (default the repo) · CACHE (default bridge/.cache) · VERBOSE=1
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
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
export const ROOT = path.resolve(process.env.ROOT || path.join(__dirname, '..'));
const CACHE = path.resolve(process.env.CACHE || path.join(__dirname, '.cache'));
const FORCE = process.argv.includes('--force');
const VERBOSE = process.env.VERBOSE === '1';

const vendorDir = path.join(ROOT, 'vendor');
const zipDir = path.join(CACHE, 'zips');

/* ------------------------------------------------------------------ */
/* 1. packages                                                        */
/* ------------------------------------------------------------------ */

function lockPackages() {
	const lock = JSON.parse(fs.readFileSync(path.join(ROOT, 'composer.lock'), 'utf8'));

	return [...lock.packages, ...(lock['packages-dev'] || [])];
}

async function download(pkg) {
	const zip = path.join(zipDir, pkg.name.replace('/', '__') + '.zip');

	if (fs.existsSync(zip) && fs.statSync(zip).size > 0) {
		return zip;
	}

	let lastError;
	for (let attempt = 1; attempt <= 3; attempt++) {
		try {
			const response = await fetch(pkg.dist.url, {
				headers: { 'User-Agent': 'paykaro-bridge', Accept: 'application/vnd.github+json' },
				redirect: 'follow',
			});

			if (!response.ok) {
				throw new Error(`HTTP ${response.status}`);
			}

			fs.writeFileSync(zip, Buffer.from(await response.arrayBuffer()));

			return zip;
		} catch (error) {
			lastError = error;
			await new Promise((resolve) => setTimeout(resolve, 1000 * attempt));
		}
	}

	throw new Error(`${pkg.name}: ${lastError.message}`);
}

function unpack(zip, dest) {
	fs.mkdirSync(dest, { recursive: true });

	const tmp = fs.mkdtempSync(path.join(CACHE, 'unpack-'));
	execFileSync('unzip', ['-q', '-o', zip, '-d', tmp], { maxBuffer: 512 * 1024 * 1024 });

	// A GitHub zipball has one top-level directory (e.g. php-fig-log-abc1234/);
	// its contents are the package. Existing entries are replaced rather than
	// merged, so a re-run after a partial install leaves exactly the zip's
	// contents behind and never a half-old tree. (rename() onto an existing
	// directory fails, so each one is removed first.)
	const [top] = fs.readdirSync(tmp);
	for (const entry of fs.readdirSync(path.join(tmp, top))) {
		const target = path.join(dest, entry);
		fs.rmSync(target, { recursive: true, force: true });
		fs.renameSync(path.join(tmp, top, entry), target);
	}

	fs.rmSync(tmp, { recursive: true, force: true });
}

async function installPackages() {
	const packages = lockPackages();
	const queue = [...packages];
	let done = 0;
	const failures = [];

	console.log(`[bootstrap] installing ${packages.length} locked packages into vendor/`);

	async function worker() {
		for (;;) {
			const pkg = queue.shift();
			if (!pkg) return;
			try {
				const zip = await download(pkg);
				unpack(zip, path.join(vendorDir, pkg.name));
				done++;
				if (VERBOSE && done % 20 === 0) console.log(`  ... ${done}/${packages.length}`);
			} catch (error) {
				failures.push(error.message);
			}
		}
	}

	// The lock is one flat dependency graph, so ordering does not matter and a
	// handful of parallel fetches is the difference between seconds and minutes.
	await Promise.all(Array.from({ length: 8 }, worker));

	if (failures.length) {
		throw new Error(`could not install ${failures.length} package(s):\n  ` + failures.join('\n  '));
	}

	console.log(`[bootstrap] ${done}/${packages.length} packages unpacked`);
}

/* ------------------------------------------------------------------ */
/* 2. autoloader                                                      */
/* ------------------------------------------------------------------ */

async function writeAutoloader() {
	const id = await loadNodeRuntime('8.3');
	const php = new PHP(id);
	useHostFilesystem(php);

	const result = await php.run({
		scriptPath: path.join(__dirname, 'php', 'gen-autoload.php'),
		relativeUri: '/',
		method: 'GET',
		$_SERVER: { argv: JSON.stringify([path.join(__dirname, 'php', 'gen-autoload.php'), ROOT]) },
	});

	const output = (result.text || '').trim();
	if (output) console.log(`[bootstrap] autoloader: ${output}`);
	if (result.errors) console.error('[bootstrap]', String(result.errors).slice(0, 800));

	if (!fs.existsSync(path.join(vendorDir, 'autoload.php'))) {
		throw new Error('the autoloader was not generated — see the errors above');
	}
}

/* ------------------------------------------------------------------ */

export async function bootstrap({ force = FORCE } = {}) {
	await ensureCertificateAuthority();

	fs.mkdirSync(zipDir, { recursive: true });

	const autoloader = path.join(vendorDir, 'autoload.php');

	if (force || !fs.existsSync(autoloader)) {
		await installPackages();
		await writeAutoloader();

		return;
	}

	if (VERBOSE) console.log('[bootstrap] vendor/ is already installed');
}

if (import.meta.url === `file://${process.argv[1]}`) {
	await bootstrap();
}
