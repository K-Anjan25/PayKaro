/**
 * composer.lock platform pre-flight.
 *
 * CI failed because the committed lock resolved five symfony packages to v8 (floor
 * >=8.4.1) on a machine running 8.4, while CI runs 8.3. That is invisible locally —
 * `composer install` on the machine that produced the lock always agrees with itself.
 * So check the lock against the platform CI actually uses, before pushing.
 *
 *   node bridge/check-lock.mjs            -> exit 1 listing offenders
 *   node bridge/check-lock.mjs 8.3.33     -> check against a specific version
 */
import fs from 'node:fs';

const LOCK = process.env.LOCK || 'composer.lock';
const cmp = (a, b) => {
    const pa = a.replace(/[^\d.].*$/, '').split('.').map(Number);
    const pb = b.split('.').map(Number);
    for (let i = 0; i < 3; i++) if ((pa[i] ?? 0) !== (pb[i] ?? 0)) return (pa[i] ?? 0) - (pb[i] ?? 0);
    return 0;
};

let lock;
try { lock = JSON.parse(fs.readFileSync(LOCK, 'utf8')); }
catch (e) { console.error(`cannot read ${LOCK}: ${e.message}`); process.exit(2); }

/* Default the target to composer.json's own `config.platform.php`: that pin is what CI
   resolves against, so asking the operator to retype it invites the two from drifting
   apart — and a stale argument here would report a clean lock that CI still rejects. */
let composer = null;
try { composer = JSON.parse(fs.readFileSync(process.env.COMPOSER_JSON || 'composer.json', 'utf8')); }
catch { /* no composer.json next to it (control runs against a bare lock) */ }

let target = process.argv[2] || null, source = 'argument';
if (!target) {
    target = composer?.config?.platform?.php ?? null;
    source = 'composer.json config.platform.php';
}
if (!target) { target = '8.3.0'; source = 'default'; }
const CI_PHP = target;

const all = [...(lock.packages ?? []), ...(lock['packages-dev'] ?? [])];
const offenders = all.filter(p => {
    const r = p.require?.php;
    if (!r) return false;
    // A single floor (">=8.4.1", "^8.4", "8.4 - 8.5") is the dangerous shape; an
    // alternatives list ("^8.2|^8.3") is fine if any arm admits CI's version.
    return r.split('|').every(arm => {
        const floor = /\d+\.\d+(\.\d+)?/.exec(arm.trim())?.[0];
        if (!floor) return false;
        return /^\s*(>=|>|^~?\d|~)/.test(arm) && cmp(floor, CI_PHP) > 0;
    });
});

const pin = lock['platform-overrides']?.php;
console.log(`lock: ${all.length} packages · target php ${CI_PHP} (from ${source}) · lock platform-overrides ${pin ?? '(none)'}`);
if (!pin && !composer?.config?.platform?.php) {
    console.log('  note: no platform pin anywhere — this lock can drift on any machine with another PHP.');
}
if (pin && cmp(pin, CI_PHP) !== 0) console.log(`  note: composer.json pins ${CI_PHP} but the lock was resolved for ${pin} — re-run \`composer update\`.`);
if (offenders.length) {
    for (const p of offenders) console.log(`  INCOMPATIBLE  ${p.name} ${p.version}  requires php ${p.require.php}`);
    console.log(`\n${offenders.length} package(s) CI's ${CI_PHP} cannot install. Run: composer update`);
    process.exit(1);
}
console.log(`  ok: every locked package's php floor admits ${CI_PHP}`);
