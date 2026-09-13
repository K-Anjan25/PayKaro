/**
 * PayKaro bridge — trust the host's certificate store, when Node does not.
 *
 * Sandboxed environments often terminate TLS with a certificate signed by their
 * own root and install that root in the system store. Node does not read the
 * system store by default, so `fetch()` to a public host fails with "unable to
 * verify the first certificate" — even though `curl` works — and the bridge
 * cannot download a single package. Node has a flag for exactly this
 * (`--use-system-ca`, Node 22.15+), but it is a *process* flag: it cannot be
 * turned on from inside a running script.
 *
 * So: probe once, and if the failure is a trust failure and the flag exists,
 * re-exec the same entry point with it. Guarded three ways — only on a
 * certificate error, only when the flag is actually supported, and only once
 * (the env guard), so it can never loop.
 *
 * Real CI and real laptops pass the probe and skip all of this.
 */

import fs from 'node:fs';
import { spawnSync } from 'node:child_process';

const GUARD = 'PAYKARO_CA_RETRY';
const FLAG = '--use-system-ca';

function flagIsSupported() {
	const probe = spawnSync(process.execPath, [FLAG, '-e', ''], { stdio: 'ignore' });

	return probe.status === 0;
}

function looksLikeTrustFailure(error) {
	const text = String(error?.cause?.message || error?.message || error);

	return /unable to verify|certificate|CERT_|self[- ]signed|SELF_SIGNED/i.test(text);
}

export async function ensureCertificateAuthority() {
	if (process.execArgv.includes(FLAG) || process.env[GUARD] === '1') {
		return;
	}

	// Nothing to fetch? Nothing to trust.
	if (!fs.existsSync(process.argv[1] || '')) {
		return;
	}

	try {
		await fetch('https://api.github.com/', {
			headers: { 'User-Agent': 'paykaro-bridge' },
			signal: AbortSignal.timeout(10_000),
		});

		return; // the host's chain is already trusted
	} catch (error) {
		if (!looksLikeTrustFailure(error) || !flagIsSupported()) {
			return; // a real network problem, or no flag to reach for — let the caller report it
		}
	}

	console.log(`[bridge] the host's CA store is not trusted by default — restarting with ${FLAG}`);

	const child = spawnSync(
		process.execPath,
		[FLAG, process.argv[1], ...process.argv.slice(2)],
		{ stdio: 'inherit', env: { ...process.env, [GUARD]: '1' } },
	);

	process.exit(child.status ?? 1);
}
