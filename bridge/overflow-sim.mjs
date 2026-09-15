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
 *   - a popup with a fixed width and no max-width hangs off a 390px phone.
 *
 * So rather than pretend to lay text out, this asserts the rules that decide
 * whether anything can overflow. Every finding names a selector and a reason a
 * browser would agree with. It is a lint-with-context, not a rendering engine:
 * a pass means "no known structural hazard", never "pixel perfect".
 *
 * RUN THE CONTROL. A checker whose parser quietly returns nothing reports nothing,
 * which is a green run over a broken file — so this aborts below 60 parsed rules,
 * prints how many it read, and is written to be pointed at a known-bad sheet:
 *
 *   CSS=/tmp/before.css INCLUDE=/dev/null node bridge/overflow-sim.mjs   -> 8+ hazards
 *   node bridge/overflow-sim.mjs                                          -> 0
 *
 * usage: node bridge/overflow-sim.mjs
 * env:   CSS=…/app.css  INCLUDE=…/datepicker.blade.php  (for control runs)
 */
import fs from 'node:fs';

const CSS = process.env.CSS || '/home/user/PayKaro/public/assets/app.css';
const INCLUDE = process.env.INCLUDE || '/home/user/PayKaro/resources/views/partials/datepicker.blade.php';

/* ----------------------------------------------------------- what may be wide */
// Real unbreakable runs from the shipped markup and copy, not 'x'*30: a token
// only causes a bug if the app actually contains one. Widths are shown for
// triage; no check below depends on them.
const TOKENS = [
    'SESSION_SECURE_COOKIE=true',
    'sunita@shreeprecision.in',
    'database/paykaro.sqlite',
    'X-Content-Type-Options',
    'GOOGLE_CLIENT_SECRET',
    'TredsOnboarding::PendingBuyerOnboard',
];
const CH_PX = 7.4;                                   // ≈ .86rem DM Sans average glyph
const px = t => String(Math.round(t.length * CH_PX)).padStart(4);

/* ---------------------------------------------------------------- css parsing */
/* Comments must go first: this sheet's banner comments contain `{`, and a
   brace-counting parser that doesn't strip them reads the file as one huge block,
   parses two rules, and reports a satisfying zero findings. */
function stripComments(css) {
    let out = '', i = 0;
    while (i < css.length) {
        if (css[i] === '/' && css[i + 1] === '*') { const e = css.indexOf('*/', i + 2); i = e < 0 ? css.length : e + 2; continue; }
        out += css[i++];
    }
    return out;
}

/* @keyframes bodies are declarations-with-braces (`0%,100%{opacity:1}`), which a
   generic "skip to the matching }" walk gets one level wrong. Dropping them whole
   is both simpler and safe — no layout rule lives inside one. */
function dropAtRules(css) {
    let out = '', i = 0;
    while (i < css.length) {
        const at = css.indexOf('@', i);
        if (at < 0) { out += css.slice(i); break; }
        out += css.slice(i, at);                    // the text *before* the at-rule
        const head = /^@(keyframes|font-face|supports)/.exec(css.slice(at));
        if (!head) { out += css[at]; i = at + 1; continue; }
        let depth = 0, j = css.indexOf('{', at);
        for (; j < css.length; j++) {
            if (css[j] === '{') depth++;
            else if (css[j] === '}') { depth--; if (!depth) { j++; break; } }
        }
        i = j;
    }
    return out;
}

/* One flat scan with an explicit stack of open blocks. A stack rather than
   recursion: recursion has to pretend the parent's block was already consumed,
   and getting that wrong makes the rule after a closed `@media` inherit the query
   (media=900) and then be filtered out as "not applicable at 1440" — which reads
   as "this rule does not exist". */
function rules(raw) {
    const css = dropAtRules(stripComments(raw));
    const out = [];
    const stack = [];                       // {kind:'sel'|'media', sel, media, bodyFrom}
    let i = 0, tokenStart = 0;
    const mediaAt = () => { for (let k = stack.length - 1; k >= 0; k--) if (stack[k].kind === 'media') return stack[k].media; return null; };
    const mediaQueryAt = () => { for (let k = stack.length - 1; k >= 0; k--) if (stack[k].kind === 'media') return stack[k].query; return null; };
    while (i < css.length) {
        const ch = css[i];
        if (ch === '{') {
            const text = css.slice(tokenStart, i).trim();
            /* `@media (max-width:900px)` and `@media print` are both queries; the
               parenthesised form is not required, and reading only that form made
               every print rule invisible to this checker. */
            const m = /^@media\s*([^{]*)$/.exec(text);
            if (m) {
                const query = m[1].replace(/^\s*\(|\)\s*$/g, '').trim();
                const mw = /max-width:\s*([\d.]+)(px|rem)?/.exec(query);
                /* A print frame is not a viewport, so it is kept out of the
                   on-screen checks (media -1) — but its query is recorded, which
                   is how the print section below finds the rules it audits. */
                stack.push({
                    kind: 'media',
                    media: /print/.test(query) ? -1 : (mw ? (mw[2] === 'rem' || !mw[2] ? +mw[1] * 16 : +mw[1]) : null),
                    query,
                });
            } else {
                stack.push({ kind: 'sel', sel: text, bodyFrom: i + 1, media: mediaAt(), query: mediaQueryAt() });
            }
            i++; tokenStart = i;
        } else if (ch === '}') {
            const f = stack.pop();
            if (f && f.kind === 'sel') {
                const sel = f.sel.trim().replace(/\s+/g, ' ');
                if (sel && !sel.startsWith('@')) out.push({ sel, body: css.slice(f.bodyFrom, i), media: f.media, query: f.query });
            }
            i++; tokenStart = i;
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
    return p.endsWith(' ' + sel);
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
// A fr track list is guarded only if *every* fr unit sits inside minmax(0,…).
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

/* A parser that finds nothing produces a green run that means nothing, so refuse
   to report on a sheet that clearly did not parse. */
if (all.length < 60) {
    console.log(`\n  ABORT: parsed only ${all.length} rule(s) from ${CSS}.`);
    console.log('         The parser or the file is wrong, and a pass in that state is meaningless.');
    process.exit(2);
}
console.log(`\nparsed ${all.length} rules (${sheetRules.length} from the sheet, ${all.length - sheetRules.length} from the include)`);

const VIEWPORTS = [1440, 1024, 768, 390];
const TRACK_SELS = ['.pkg-grid--4', '.pkg-grid--3', '.pkg-grid--2', '.cards-row', '.pkg-invoicemeta',
    '.pkg-detail-grid', '.sec-head', '.legal-body', '.legal-head', '.hero-inner', '.page-footer .container',
    '.legal-toc ol'];

console.log('\n-- grid tracks that may not shrink (blow-out risk) --');
{
    let hits = 0;
    for (const w of VIEWPORTS) {
        for (const sel of TRACK_SELS) {
            const v = propOf(sel, 'grid-template-columns', w);
            const g = trackGuard(v);
            if (g.startsWith('partial')) {
                hits++;
                bad(`${sel} has unguarded fr tracks at ${w}px`, `grid-template-columns:${v}`);
            }
        }
    }
    if (!hits) ok(`all ${TRACK_SELS.length} measured grids keep minmax(0,…) at ${VIEWPORTS.join('/')}px`);

    /* Then sweep *every* grid in the sheet, not just the curated list above: a list
       can only catch what its author already thought to look for, which is exactly
       how the responsive overrides slipped through in the first place. */
    const loose = [];
    for (const r of all) {
        const m = /grid-template-columns:\s*([^;}]+)/.exec(r.body);
        if (!m) continue;
        const v = m[1].trim();
        if (/auto-fill|auto-fit/.test(v)) continue;         // self-limiting by construction
        if (trackGuard(v).startsWith('partial')) loose.push({ sel: r.sel, v, media: r.media });
    }
    if (loose.length) {
        for (const g of loose.slice(0, 8)) {
            bad(`${g.sel} has unguarded fr tracks`,
                `grid-template-columns:${g.v}${g.media ? `  (@media max-width:${g.media}px)` : ''}`);
        }
        if (loose.length > 8) bad(`…and ${loose.length - 8} more unguarded grids in the sheet`);
    } else ok('no grid anywhere in the sheet has an unguarded fr track');
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
    if (!r) notes++, console.log('  --   .pkg-cal not in this sheet (control run)');
    else if (!/max-width:\s*calc\(100vw/.test(r.body)) bad('.pkg-cal can exceed the viewport', r.body.trim().replace(/\s+/g, ' ').slice(0, 120));
    else ok('.pkg-cal is clamped to calc(100vw − 1.5rem)');
    // A clamp only helps if the JS positioning the popup re-measures afterwards.
    if (inc) {
        if (/window\.innerWidth\s*-\s*pop\.offsetWidth/.test(inc)) {
            bad('place() computes left from the *declared* width', 'the clamped popup can still hang off the right edge');
        } else if (/pop\.offsetWidth/.test(inc)) {
            ok('place() re-measures after the clamp instead of trusting the declared width');
        }
    }
}

console.log('\n-- the printed claim packet (a filing cited by its GSTIN and number) --');
{
    const print = all.filter(r => (r.query || '').includes('print'));

    if (!print.length) {
        bad('no @media print block', 'the packet prints with app chrome, the dark palette and schedules split across pages');
    } else {
        ok(`${print.length} print rule(s) parsed`);

        const hides = sel => print.some(r => matches(r, sel) && /display:\s*none/.test(r.body));
        const chrome = ['.app-util', '.app-head', '.app-footer', '.theme-toggle', '.claim-actions', '.pkg-btn'];
        const kept = chrome.filter(s => !hides(s));
        if (kept.length) bad(`print keeps ${kept.join(', ')}`, 'navigation and buttons do not belong on a statutory filing');
        else ok(`print hides the app chrome (${chrome.join(', ')})`);

        /* The palette is re-declared for print because `html.dark` would win
           otherwise — so the copy is compared against :root. Drift here prints a
           page in a palette nobody chose. */
        const decls = body => Object.fromEntries([...body.matchAll(/(--[\w-]+)\s*:\s*([^;}]+)/g)].map(m => [m[1], m[2].trim()]));
        const rootVars = decls((all.find(r => r.sel === ':root') || { body: '' }).body);
        const light = print.find(r => parts(r).includes('html.dark'));

        if (!light) {
            bad('print does not re-assert the light palette', 'a member printing from dark mode files a dark page');
        } else {
            const vars = decls(light.body);
            const drifted = Object.entries(vars).filter(([k, v]) => rootVars[k] && v !== rootVars[k] && v !== 'none');
            if (drifted.length) {
                bad(`print palette drifts from :root (${drifted.map(([k]) => k).join(', ')})`,
                    drifted.map(([k, v]) => `${k}: ${v} vs ${rootVars[k]}`).slice(0, 3).join('  ·  '));
            } else {
                ok(`print re-asserts ${Object.keys(vars).length} light token(s), each equal to :root (shadows off)`);
            }
        }

        const whole = ['.claim-highlight', '.claim-box', '.claim-annexure', '.claim-table-wrap', '.claim-total-row'];
        const split = whole.filter(s => !print.some(r => matches(r, s) && /break-inside:\s*avoid/.test(r.body)));
        if (split.length) bad(`print lets ${split.join(', ')} break across pages`, 'a page break through the interest schedule makes the filing unreadable');
        else ok(`print keeps every schedule whole (${whole.length} selectors, plus table rows)`);

        const foot = print.find(r => matches(r, '.claim-print-foot') && /display:\s*(flex|block|table)/.test(r.body));
        if (!foot) bad('.claim-print-foot is never shown in print', 'the page has to carry the GSTIN and invoice number it is cited by');
        else if (!/position:\s*fixed/.test(foot.body)) bad('.claim-print-foot is not position:fixed', 'a footer that is not fixed prints once, not on every page');
        else ok('.claim-print-foot repeats on every printed page (position:fixed)');
    }

    const page = /@page\s*\{([^}]*)\}/s.exec(sheet);
    if (!page || !/margin\s*:/.test(page[1])) bad('no @page margin', 'the printer default clips the running footer');
    else ok(`@page margin set (${page[1].trim().replace(/\s+/g, ' ')})`);
}

console.log('\n-- tokens that triggered this (width if unbroken) --');
for (const t of [...TOKENS].sort((a, b) => b.length - a.length)) console.log(`  ${px(t)}px  ${t}`);

console.log(`\n${fail ? `${fail} hazard(s) remain` : 'no structural overflow hazards'} — ${notes} check(s) passed`);
process.exit(fail ? 1 : 0);
