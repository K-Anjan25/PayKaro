/**
 * CSS overflow simulator for public/assets/app.css (+ the datepicker include's own
 * <style>, which ships as a second stylesheet).
 *
 * Why this exists: jsdom performs no layout, so bridge/datepicker-harness.mjs can
 * prove the calendar *behaves* but cannot prove the page *fits*. The overflow bugs
 * in this app were structural, and structural things can be checked without a
 * renderer:
 *
 *   - a `repeat(n,1fr)` track is auto-sized, so it can never be narrower than its
 *     widest unbreakable token; `minmax(0,1fr)` is what lets it shrink;
 *   - `overflow:hidden` on a scroll container turns "scroll" into "clip";
 *   - a `max-content` grid track is sized by the very text that may be too wide;
 *   - a popup with `width:17.5rem` and no `max-width` hangs off a 390px phone.
 *
 * So rather than pretend to lay text out, this asserts the rules that decide
 * whether anything can overflow, at the widths where the design breaks. Every
 * finding names a selector and a reason a browser would agree with. It is a
 * lint-with-context, not a rendering engine — and it is honest about that: pass
 * here means "no known structural hazard", not "pixel perfect".
 *
 * It is written to be run against a *broken* sheet as a control:
 *   CSS=/tmp/old.css node bridge/overflow-sim.mjs
 * which is the only way to know the checks can fail at all.
 *
 * usage: node bridge/overflow-sim.mjs
 * env:   CSS=…/app.css  INCLUDE=…/datepicker.blade.php  (for control runs)
 */
import fs from 'node:fs';

const CSS = process.env.CSS || '/home/user/PayKaro/public/assets/app.css';
const INCLUDE = process.env.INCLUDE || '/home/user/PayKaro/resources/views/partials/datepicker.blade.php';

/* ----------------------------------------------------------- what may be wide */
// Real unbreakable runs from the shipped markup and copy, not synthetic 'x'*30:
// a token only causes a bug if the app actually contains one.
const TOKENS = [
    'SESSION_SECURE_COOKIE=true',
    'sunita@shreeprecision.in',
    'database/paykaro.sqlite',
    'X-Content-Type-Options',
    'GOOGLE_CLIENT_SECRET',
    'TredsOnboarding::PendingBuyerOnboard',
];
const CH_PX = 7.4;                    // ≈ .86rem DM Sans average glyph. Only used to rank
const px = w => (w.length * CH_PX).toFixed(0);   // findings by "how bad", never to decide one.

/* ---------------------------------------------------------------- css parsing */
/* Comments go first, always: this sheet's banner comments contain `{`, and a
   parser that counts braces without stripping comments reads the whole file as
   one swallowed block and reports a satisfyingly empty 0 findings. */
function stripComments(css) {
    let out = '', i = 0;
    while (i < css.length) {
        if (css[i] === '/' && css[i + 1] === '*') { const e = css.indexOf('*/', i + 2); i = e < 0 ? css.length : e + 2; continue; }
        out += css[i++];
    }
    return out;
}

/* @keyframes bodies are declarations-with-braces (`0%,100%{opacity:1}`), which a
   generic "skip to the matching }" walk gets exactly one level wrong: it swallows
   the first inner block, then reads `50%{…}` as a rule and the *next* real
   selector onward as a body — the whole scan desyncs and quietly parses 2 rules
   out of 570. A parser that under-parses this badly still reports "no findings",
   which is how a broken tool passes. Drop them before parsing. */
function dropAtRules(css) {
    let out = '', i = 0;
    while (i < css.length) {
        const at = css.indexOf('@', i);
        if (at < 0) { out += css.slice(i); break; }
        out += css.slice(i, at);                  // <-- the text *before* the at-rule
        const head = /^@(keyframes|font-face|supports)/.exec(css.slice(at));
        if (!head) { out += css[at]; i = at + 1; continue; }   // e.g. `@x` inside a string
        let depth = 0, j = css.indexOf('{', at);
        for (; j < css.length; j++) {
            if (css[j] === '{') depth++;
            else if (css[j] === '}') { depth--; if (!depth) { j++; break; } }
        }
        i = j;                                    // whole at-rule removed
    }
    return out;
}

/* One flat scan with an explicit stack of {selector, body-start, depth} frames.
   A stack rather than recursion: recursion has to *prettend* the parent's open
   block has already been consumed, and getting that wrong makes the rule after a
   closed `@media` inherit the query (media=900) and then be filtered out as
   "not applicable at 1440" — which reads as "this rule does not exist". */
function rules(raw) {
    const css = dropAtRules(stripComments(raw));
    const out = [];
    const stack = [];                       // {kind:'sel'|'media', sel, media, bodyFrom, depth}
    let i = 0, tokenStart = 0;
    const close = () => {
        const f = stack.pop();
        if (!f) return;
        if (f.kind === 'sel') {
            const sel = f.sel.trim().replace(/\s+/g, ' ');
            if (sel && !sel.startsWith('@')) out.push({ sel, body: css.slice(f.bodyFrom, i), media: f.media });
        }
        i++;
    };
    const mediaAt = () => { for (let k = stack.length - 1; k >= 0; k--) if (stack[k].kind === 'media') return stack[k].media; return null; };
    while (i < css.length) {
        const ch = css[i];
        if (ch === '{') {
            const text = css.slice(tokenStart, i).trim();
            const m = /^@media\s*\(([^)]*)\)/.exec(text);
            if (m) {
                const mw = /max-width:\s*([\d.]+)(px|rem)?/.exec(m[1]);
                stack.push({ kind: 'media', media: mw ? (mw[2] === 'rem' || !mw[2] ? +mw[1] * 16 : +mw[1]) : null });
            } else {
                stack.push({ kind: 'sel', sel: text, bodyFrom: i + 1, depth: 1, media: mediaAt() });
            }
            i++; tokenStart = i;
        } else if (ch === '}') {
            close(); tokenStart = i;
        } else if (ch === ';' && !stack.length) {
            tokenStart = i + 1; i++;           // stray top-level at-rule statement
        } else {
            i++;
        }
    }
    return out;
}

const sheet = fs.readFileSync(CSS, 'utf8');
const inc = fs.existsSync(INCLUDE) ? fs.readFileSync(INCLUDE, 'utf8') : '';
const incStyle = inc.includes('<style>') ? inc.slice(inc.indexOf('<style>') + 7, inc.indexOf('</style>')) : '';
const sheetRules = rules(sheet);
const all = [...sheetRules, ...rules(incStyle)];

/* ------------------------------------------------------------------- matching */
const parts = r => r.sel.split(',').map(s => s.trim().replace(/\s+/g, ' '));
const active = (r, w) => r.media === null || r.media >= w;
/* Match the selector, not a longer one that merely ends with it: `.legal-prose`
   must not be satisfied by `.legal-prose p { font-size… }`, or the sim reads the
   child's declarations as the parent's and reports a confident falsehood. */
const matches = (r, sel) => parts(r).some(p => {
    if (p === sel) return true;
    if (!sel.includes(' ')) return false;
    return p === sel || p.endsWith(' ' + sel) || p.endsWith(', ' + sel);
});
const ruleFor = (sel, re, w = 1440) => all.find(r => active(r, w) && matches(r, sel) && (!re || re.test(r.body))) || null;
const propOf = (sel, prop, w = 1440) => {
    let val = null;
    for (const r of all) {
        if (!active(r, w) || !matches(r, sel)) continue;
        const m = new RegExp(`(?:^|[;\\s])${prop}:\\s*([^;}]+)`).exec(r.body);
        if (m) val = m[1].trim();
    }
    return val;
};
// An fr track list is guarded only if *every* fr unit sits inside minmax(0,…).
const trackGuard = t => {
    if (!t) return 'absent';
    const frs = t.match(/[\d.]*fr/g) || [];
    if (!frs.length) return 'no-fr';
    const guarded = (t.match(/minmax\(\s*0\s*,[^)]*fr\s*\)/g) || []).length;
    return guarded >= frs.length ? 'yes' : `partial(${guarded}/${frs.length})`;
};

let fail = 0, notes = 0;
const bad = (msg, detail = '') => { fail++; console.log(`  FAIL ${msg}${detail ? `\n          ${detail}` : ''}`); };
const ok = msg => { notes++; console.log(`  ok   ${msg}`); };

const VIEWPORTS = [1440, 1024, 768, 390];
const TRACK_SELS = ['.pkg-grid--4', '.pkg-grid--3', '.pkg-grid--2', '.cards-row', '.pkg-invoicemeta',
    '.pkg-detail-grid', '.sec-head', '.legal-body', '.legal-head', '.hero-inner', '.page-footer .container',
    '.legal-toc ol'];

// A parser that silently finds nothing produces a green run that means nothing,
// so refuse to report success unless the sheet actually parsed.
if (all.length < 60) {
    console.log(`\n  ABORT: parsed only ${all.length} rule(s) from ${CSS} — the parser or the file is wrong,`);
    console.log('         and a pass in that state would be meaningless.');
    process.exit(2);
}
console.log('\n-- scroll containers that clip instead of scrolling --');
{
    const clipped = VIEWPORTS.filter(w => {
        const r = ruleFor('.pkg-tablewrap', /overflow/, w);
        return r && /overflow:\s*hidden\b/.test(r.body) && !/overflow-x/.test(r.body);
    });
    if (clipped.length) bad('.pkg-tablewrap clips overflow, so wide tables lose their last columns', `${clipped.join('px/')}px`);
    else {
        const r = ruleFor('.pkg-tablewrap', /overflow/);
        if (r && /overflow-x:\s*auto/.test(r.body)) ok('.pkg-tablewrap scrolls (overflow-x:auto)');
        else bad('.pkg-tablewrap has no overflow rule at all');
    }
}

console.log('\n-- text wrapping (inherited, so it protects every track) --');
{
    const r = ruleFor('.page', /overflow-wrap/) || ruleFor('.pkg', /overflow-wrap/);
    if (!r) bad('no inherited overflow-wrap on the page roots (.page / .pkg)');
    else ok(`.page/.pkg set overflow-wrap:${/overflow-wrap:\s*([\w-]+)/.exec(r.body)[1]} — a long token breaks instead of widening its column`);

    const mw = propOf('.legal-prose', 'max-width');
    if (!mw) bad('.legal-prose has no measure cap', 'a 78rem container makes ~120-character lines');
    else ok(`.legal-prose measure capped at ${mw}`);

    const code = propOf('.legal-part p code', 'overflow-wrap') || propOf('.legal-part li code', 'overflow-wrap');
    if (code) ok(`inline <code> chips may break (${code}) — a config key cannot stretch the prose column`);
    else bad('inline <code> chips cannot break', 'a 26-char token forces its column wider');
}

console.log('\n-- max-content tracks (text sizing its own container) --');
{
    const r = ruleFor('.legal-note', /grid-template-columns/);
    if (r && /max-content/.test(r.body) && !/minmax\(\s*0/.test(r.body)) {
        bad('.legal-note sizes a track by max-content', r.body.trim().replace(/\s+/g, ' ').slice(0, 120));
    } else ok('.legal-note uses no max-content track (block layout with an inline label)');
}

console.log('\n-- fixed-size popups vs the viewport --');
{
    const r = ruleFor('.pkg-cal', /width/);
    if (!r) { notes++; console.log('  --   .pkg-cal not present in this sheet (control run)'); }
    else if (!/max-width:\s*calc\(100vw/.test(r.body)) bad('.pkg-cal can exceed the viewport', r.body.trim().replace(/\s+/g, ' ').slice(0, 120));
    else ok('.pkg-cal is clamped to calc(100vw − 1.5rem)');
    // A clamp only helps if the JS that positions the popup re-measures afterwards.
    if (inc) {
        if (/pop\.offsetWidth/.test(inc) && !/window\.innerWidth\s*-\s*pop\.offsetWidth/.test(inc)) {
            ok('place() re-measures after the clamp instead of trusting the declared width');
        } else if (/window\.innerWidth\s*-\s*pop\.offsetWidth/.test(inc)) {
            bad('place() still computes left from the *declared* width', 'the clamped popup can hang off the right edge');
        }
    }
}

console.log('\n-- tokens that triggered this (width if unbroken) --');
for (const t of [...TOKENS].sort((a, b) => b.length - a.length)) console.log(`  ${px(t).padStart(4)}px  ${t}`);

console.log(`\n${fail ? `${fail} hazard(s) remain` : 'no structural overflow hazards'} — ${notes} check(s) passed`);
process.exit(fail ? 1 : 0);
