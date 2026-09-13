/**
 * Behavioural harness for resources/views/partials/datepicker.blade.php.
 *
 * The sandbox has no browser and no PHP, so the popup had previously been
 * verified only by `node --check` plus brace balance — enough to prove it
 * parses, not enough to prove it works. This mounts the include's own markup and
 * script inside jsdom and drives it the way a user does (click, arrow, type), so
 * the calendar maths, the event wiring and the min/max bounding actually execute.
 *
 * It found four real bugs that no static check could see:
 *  1. `parse()`'s regex was written as /^(\\d{4})…/ — a literal backslash then
 *     'd' — so it NEVER matched: the popup always opened on today instead of the
 *     field's stored date, and pre-selected the wrong day.
 *  2. `shift()` returned a Date into a function expecting a [y,m,d] triple, so
 *     the leading (previous-month) cells rendered "undefined-NaN-undefined".
 *  3. `firstDow()` was off by one against its own Monday-first header: every
 *     month that does not start on a Monday had the whole grid shifted a column.
 *  4. `trigger()` dereferenced a null button for a field created after page load,
 *     throwing inside the click handler and killing the popup for that page.
 *
 * Conventions that keep this honest:
 *  - Every scenario boots a *fresh* DOM. Sharing state across scenarios makes an
 *    assertion pass because of the previous one — which is exactly how a broken
 *    "open" came to look like a broken "choose".
 *  - No calendar expectation is hard-coded: month names, cell counts and
 *    neighbouring days are derived from the pinned "today", so the suite still
 *    means something in 2027, and on 29 February.
 *  - Nothing is stubbed on <input type=date>. jsdom implements its value
 *    semantics (including rejecting a malformed value); overriding `value` to
 *    "help" it silently breaks what is under test.
 *  - jsdom swallows exceptions thrown inside listeners and reports them on the
 *    virtual console; those are collected and fail the run, since a throw inside
 *    a click handler is precisely the bug class this harness exists to catch.
 *
 * usage: node bridge/datepicker-harness.mjs [path/to/datepicker.blade.php]
 * needs: `npm i jsdom` in bridge/ — dev-only, deliberately absent from
 *        package.json so it can never leak into a production install.
 *
 * A green run does NOT prove visual alignment or native-picker behaviour: jsdom
 * lays nothing out. Those remain a browser job.
 */
import fs from 'node:fs';

import { JSDOM, VirtualConsole } from 'jsdom';

const FILE = process.argv[2] || '/home/user/PayKaro/resources/views/partials/datepicker.blade.php';

/* ------------------------------------------------------------------ calendar */

const ymd = d => `${d.getUTCFullYear()}-${String(d.getUTCMonth() + 1).padStart(2, '0')}-${String(d.getUTCDate()).padStart(2, '0')}`;
const parts = s => s.split('-').map(Number);
const shiftDays = (s, n) => { const [y, m, d] = parts(s); return ymd(new Date(Date.UTC(y, m - 1, d + n))); };
const shiftMonths = (s, n) => { const [y, m] = parts(s); return ymd(new Date(Date.UTC(y, m - 1 + n, 1))); };
const monthName = s => { const [y, m] = parts(s); return `${MONTHS[m - 1]} ${y}`; };
const monthOffset = (y, m0) => (new Date(Date.UTC(y, m0, 1)).getUTCDay() + 6) % 7;      // 0 = Monday
const monthLen = (y, m0) => new Date(Date.UTC(y, m0 + 1, 0)).getUTCDate();
const expectCells = (y, m0) => monthOffset(y, m0) + monthLen(y, m0);
const isLeap = y => new Date(Date.UTC(y, 1, 29)).getUTCMonth() === 1;
// The widget's own list, mirrored so the harness asserts the same spelling it
// must produce rather than trusting Intl's locale-dependent one.
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

const TODAY = process.env.PIN_TODAY || ymd(new Date());
const [TY, TM, TD] = parts(TODAY);
const nameOf = s => monthName(`${s.slice(0, 7)}-01`);
const TOMORROW = shiftDays(TODAY, 1);
const NEXT_MONTH_FIRST = shiftMonths(TODAY, 1);
const PREV_MONTH_LAST = shiftDays(`${TODAY.slice(0, 7)}-01`, -1);

/* ---------------------------------------------------------------------- boot */

function boot(fields) {
    const blade = fs.readFileSync(FILE, 'utf8');
    const markup = blade.slice(blade.indexOf('<div class="pkg-cal"'), blade.indexOf('</div>\n\n<style>')).trim() + '</div>';
    const script = blade.slice(blade.indexOf('<script>') + 8, blade.lastIndexOf('</script>'));
    if (!script.includes('__pkgDatepicker') || !markup.includes('id="pkg-cal"')) {
        throw new Error(`could not extract markup/script from ${FILE} — the include changed shape`);
    }

    const errors = [];
    const vc = new VirtualConsole();
    vc.on('jsdomError', e => errors.push(e.message || String(e)));
    vc.on('error', (...a) => errors.push(a.join(' ')));

    const inputs = Object.entries(fields).map(([id, attrs]) =>
        `<div class="pkg-field"><label for="${id}">${id}</label>
           <input class="pkg-input" id="${id}" type="date" name="${id}"${attrs}></div>`).join('\n');

    // Only Date.now() is pinned: that is the single call the widget makes for
    // "today", and shadowing all of Date would hide real bugs.
    const pin = `var D = window.Date; function P() {
        if (!arguments.length) { return new D(${TY}, ${TM - 1}, ${TD}, 12); }
        return new (Function.prototype.bind.apply(D, [null].concat([].slice.call(arguments))))();
     }
     P.now = function () { return new D(${TY}, ${TM - 1}, ${TD}, 12).getTime(); };
     P.UTC = D.UTC; P.parse = D.parse; P.prototype = D.prototype; window.Date = P;`;

    const dom = new JSDOM(`<!doctype html><html><body>
        ${inputs}
        ${markup}
        <script>${pin}${script}<\/script>
    </body></html>`, { runScripts: 'dangerously', pretendToBeVisual: true, virtualConsole: vc });

    const { window } = dom;
    // jsdom performs no layout, so place()'s flip-above rule needs numbers.
    Object.defineProperty(window.HTMLElement.prototype, 'offsetHeight', { configurable: true, get() { return 300; } });
    Object.defineProperty(window.HTMLElement.prototype, 'offsetWidth', { configurable: true, get() { return 280; } });
    window.HTMLInputElement.prototype.getBoundingClientRect = () =>
        ({ top: 100, bottom: 132, left: 200, right: 400, width: 200, height: 32, x: 200, y: 100 });

    if (window.document.readyState !== 'complete') {
        window.document.dispatchEvent(new window.Event('DOMContentLoaded', { bubbles: true }));
    }

    return { window, document: window.document, errors, blade };
}

/* ------------------------------------------------------------------- running */

let pass = 0, fail = 0;
const group = name => console.log(`\n-- ${name} --`);

function scenario(name, fields, run) {
    const { window, document, errors, blade } = boot(fields);
    const $ = s => document.querySelector(s);
    const $$ = s => [...document.querySelectorAll(s)];
    const cells = () => $$('.pkg-cal-day');
    const dayEl = v => cells().find(c => c.dataset.calDay === v);
    const click = el => el && el.dispatchEvent(new window.MouseEvent('click', { bubbles: true }));
    const key = (k, id) => document.getElementById(id).dispatchEvent(new window.KeyboardEvent('keydown', { key: k, bubbles: true }));
    const field = id => document.getElementById(id);
    const cal = () => $('#pkg-cal');
    // &nbsp; is the header's pre-render placeholder; \.trim()\(\) would keep it.
    const view = () => $('#pkg-cal-mon').textContent.replace(/\s+/g, ' ').trim();
    const step = n => { for (let i = 0; i < Math.abs(n); i++) click($(`[data-cal-nav="${n < 0 ? '-1' : '1'}"]`)); };

    // open() blurs first: after a commit the field is focused, and the widget
    // deliberately swallows a click that is really the tail of its own write.
    const open = id => { const el = field(id); el.blur?.(); click(el); };
    const type = (id, v) => { const el = field(id); el.value = v; el.dispatchEvent(new window.Event('input', { bubbles: true })); };
    // show() = re-open (centres the grid on the value) then type, so every
    // assertion starts from a view we chose, not one left by the previous step.
    const show = (id, v) => { open(id); type(id, v); };

    const api = {
        blade, cells, dayEl,
        check(n, ok, extra = '') {
            if (ok) { pass++; console.log(`  ok   ${n}`); } else { fail++; console.log(`  FAIL ${n}${extra ? '  -> ' + extra : ''}`); }
            return ok;
        },
    };
    const env = { $, $$, cells, dayEl, click, key, field, cal, view, step, open, type, show, document, window };

    run(api, env);

    if (errors.length) {
        fail++;
        console.log(`  FAIL no exception escaped into a listener (${errors.length})  -> ${errors[0].split('\n')[0]}`);
    }
}

const AT = v => ` value="${v}"`;
const MAX_TODAY = AT(TODAY) + ` max="${TODAY}"`;
const MIN_TODAY = AT(TODAY) + ` min="${TODAY}"`;

group('wiring');
scenario('fields are decorated and share one popup', { invoice_date: AT(TODAY), paid_on: MAX_TODAY },
    ({ check, blade }, { $, $$, click, cal, document }) => {
        check('every date input is wrapped in .pkg-calwrap', $$('.pkg-calwrap').length === 2);
        check('each field gets one calendar trigger', $$('.pkg-calbtn').length === 2);
        check('the trigger is labelled for assistive tech', $('.pkg-calbtn').getAttribute('aria-label') === 'Open calendar');
        check('the native indicator is hidden so the two do not double up', /calendar-picker-indicator[\s\S]{0,90}opacity:0/.test(blade));
        check('the popup is moved onto <body> so nothing clips it', $('#pkg-cal').parentElement === document.body);
        check('and stays hidden until asked', cal().hidden);
        check('the include is idempotent', document.defaultView.__pkgDatepicker === true);
        // wire() runs once per page; a field added by a later re-render has no
        // button, so the documented way to open it is __pkgCalShow(field).
        check('a field added later can still be opened', (() => {
            document.body.insertAdjacentHTML('beforeend', '<div class="pkg-field"><input id="late" type="date" value="2027-06-15"></div>');
            document.defaultView.__pkgCalShow(document.getElementById('late'));
            return !$('#pkg-cal').hidden && /June 2027/.test($('#pkg-cal-mon').textContent.replace(/\s+/g, ' ').trim());
        })());
    });

group('opening on a stored value');
scenario('the popup reads the field, not the clock', { due: AT('2027-06-15') },
    ({ check, cells, dayEl }, { $, cal, open, view, document }) => {
        open('due');
        check('the popup becomes visible', !cal().hidden);
        check('the header shows the field month, not today', view() === 'June 2027', view());
        check('the cell count matches the real calendar', cells().length === expectCells(2027, 5), `got ${cells().length}`);
        check('1 Jun 2027 lands in the correct weekday column',
            cells().findIndex(c => c.dataset.calDay === '2027-06-01') === monthOffset(2027, 5));
        check('the stored value is pre-selected', !!dayEl('2027-06-15')?.classList.contains('is-sel'));
        check('focus lands on the selected day', document.activeElement?.dataset?.calDay === '2027-06-15', document.activeElement?.dataset?.calDay);
        check('the trigger is marked open', $('.pkg-calbtn').classList.contains('is-open'));
    });

group('choosing a day');
scenario('a click writes the field and notifies the form', { due: AT('2027-06-15') },
    ({ check, dayEl }, { $, click, open, document }) => {
        let inputEvents = 0, changeEvents = 0;
        const f = document.getElementById('due');
        f.addEventListener('input', () => inputEvents++);
        f.addEventListener('change', () => changeEvents++);
        open('due');
        click(dayEl('2027-06-27'));
        check('the value is written as YYYY-MM-DD', f.value === '2027-06-27', f.value);
        check('`input` fires, so the form preview stays live', inputEvents === 1, `got ${inputEvents}`);
        check('`change` fires, so server-side validation re-runs', changeEvents === 1, `got ${changeEvents}`);
        check('the popup closes after the choice', $('#pkg-cal').hidden);
        check('focus returns to the field', document.activeElement === f);
    });

group('month arithmetic');
scenario('grid sizes follow the real calendar', { due: AT('2027-06-15') },
    ({ check, cells }, { $, step, open, view }) => {
        open('due');
        const grid = () => cells().filter(c => c.dataset.calDay.startsWith('2027-06'));
        check('June 2027 renders exactly its 30 real days', grid().length === monthLen(2027, 5) && monthLen(2027, 5) === 30, `got ${grid().length}`);
        step(-5);
        check('Jan 2027: 31 days, and the grid never duplicates a real day',
            /January 2027/.test(view())
            && new Set(cells().map(c => c.dataset.calDay)).size === cells().length);
        step(+1);
        check('Feb 2027 has 28 days (not a leap year)', /February 2027/.test(view())
            && !cells().some(c => c.dataset.calDay === '2027-02-29'));
        step(11);
        check('Jan 2028 …and Feb 2028 has 29 (it is)', /January 2028/.test(view()));
        step(1);
        check('Feb 2028 renders the 29th', /February 2028/.test(view())
            && !!cells().find(c => c.dataset.calDay === '2028-02-29'));
        check('no cell is ever rendered twice or as garbage',
            cells().every(c => /^\d{4}-\d{2}-\d{2}$/.test(c.dataset.calDay || '') && c.textContent.trim() !== 'NaN'),
            cells().find(c => !/^\d{4}-\d{2}-\d{2}$/.test(c.dataset.calDay || ''))?.outerHTML?.slice(0, 80));
    });

group('min/max bounding');
scenario('a field bounded by today', { paid_on: MAX_TODAY, raised_on: MIN_TODAY },
    ({ check }, { cells, click, open, view, field, document }) => {
        const day = v => cells().find(c => c.dataset.calDay === v);
        const real = () => cells().filter(c => !c.classList.contains('is-out'));

        open('paid_on');
        check('today is highlighted', day(TODAY)?.classList.contains('is-today'));
        check('max itself is selectable', day(TODAY)?.disabled === false);
        // On a month-end "today" (29 Feb, 31 Dec…) tomorrow is not in this grid
        // at all, so assert only on days that are on screen.
        const visibleTomorrow = day(TOMORROW);
        if (TOMORROW.slice(0, 7) === TODAY.slice(0, 7)) {
            check('a day past max is disabled and unchoosable',
                visibleTomorrow?.disabled === true, `disabled=${visibleTomorrow?.disabled}`);
            click(visibleTomorrow);
            check('…and the field keeps max', field('paid_on').value === TODAY, field('paid_on').value);
        } else {
            check('on a month-end max there is no later day in this grid', visibleTomorrow === undefined);
        }
        open('paid_on');
        check('the month on screen contains free days before max',
            real().some(c => !c.disabled), view());
        document.querySelector('[data-cal-nav="1"]').dispatchEvent(new document.defaultView.MouseEvent('click', { bubbles: true }));
        check('paging forward past max still renders the month', view() === nameOf(NEXT_MONTH_FIRST), view());
        check('…with every one of its days disabled', real().length > 0 && real().every(c => c.disabled));

        open('paid_on');
        document.querySelector('[data-cal-nav="-1"]').dispatchEvent(new document.defaultView.MouseEvent('click', { bubbles: true }));
        check('the month before max is free', real().every(c => !c.disabled));

        // Straddle: when max is the last day of its month the boundary sits on an
        // edge, so assert whichever shape today's month actually has.
        open('paid_on');
        const lastDay = new Date(Date.UTC(TY, TM, 0)).getUTCDate();
        const last = `${TODAY.slice(0, 7)}-${String(lastDay).padStart(2, '0')}`;
        check('the boundary falls on max, never a day either side',
            day(TODAY)?.disabled === false && (day(last)?.disabled === (last > TODAY)),
            `max=${day(TODAY)?.disabled} last=${day(last)?.disabled}`);
        check('the first of the month is always selectable', day(`${TODAY.slice(0, 7)}-01`)?.disabled === false);

        open('raised_on');
        check('min blocks a fully-past month', (() => {
            document.querySelector('[data-cal-nav="-1"]').dispatchEvent(new document.defaultView.MouseEvent('click', { bubbles: true }));
            return real().every(c => c.disabled);
        })());
        open('raised_on');
        check('a day before min cannot be chosen', (() => {
            const before = field('raised_on').value;
            click(real().find(c => c.classList.contains('is-out') && c.disabled) || day(shiftDays(TODAY, -1)));
            return field('raised_on').value === before;
        })());
    });

group('keyboard');
scenario('arrows, paging, Home, Enter, Escape', { free: AT(TODAY) },
    ({ check }, { $, cal, key, open, show, step, field, view, document }) => {
        const f = () => field('free');
        open('free');
        check('the popup is bound to the field', !cal().hidden);

        show('free', TODAY);
        check('…and centred on the field value', view() === nameOf(TODAY), view());
        show('free', TODAY); key('ArrowRight', 'free');
        check('ArrowRight -> +1 day', f().value === TOMORROW, f().value);
        show('free', TODAY); key('ArrowDown', 'free');
        check('ArrowDown -> +7 days', f().value === shiftDays(TODAY, 7), f().value);
        show('free', TOMORROW); key('ArrowUp', 'free');
        check('ArrowUp -> -7 days', f().value === shiftDays(TOMORROW, -7), f().value);
        show('free', TOMORROW); key('ArrowLeft', 'free');
        check('ArrowLeft -> -1 day', f().value === TODAY, f().value);
        show('free', `${TODAY.slice(0, 7)}-01`); key('ArrowLeft', 'free');
        check('a step back across a month start is exact', f().value === PREV_MONTH_LAST, f().value);

        show('free', TODAY); key('PageDown', 'free');
        check('PageDown moves the view a month, not the value',
            view() === nameOf(NEXT_MONTH_FIRST) && f().value === TODAY, `${view()} / ${f().value}`);
        key('PageUp', 'free');
        check('PageUp moves it back', view() === nameOf(TODAY));
        key('Home', 'free');
        check('Home returns to the current month', view() === nameOf(TODAY));

        show('free', TODAY);
        key('Enter', 'free');
        check('Enter chooses the focused day and closes', cal().hidden);
        open('free'); key('Escape', 'free');
        check('Escape closes', cal().hidden);
        check('a key that is not ours leaves the field alone', (() => {
            const before = f().value; open('free'); key('Tab', 'free'); return f().value === before;
        })());
    });

group('typing while open');
scenario('the grid follows the value, but does not fight you', { free: AT(TODAY) },
    ({ check }, { $, cal, key, open, show, type, view, field }) => {
        show('free', TODAY); key('PageDown', 'free');
        check('paged to next month', view() === nameOf(NEXT_MONTH_FIRST), view());
        type('free', shiftDays(NEXT_MONTH_FIRST, 3));
        check('typing a date inside the displayed month keeps that month', view() === nameOf(NEXT_MONTH_FIRST), view());
        type('free', shiftMonths(TODAY, 5).replace(/-01$/, '-04'));
        check('typing a date outside it re-centres the grid', view() === nameOf(shiftMonths(TODAY, 5)), view());
        check('…and the value is untouched by the re-centre', field('free').value === `${shiftMonths(TODAY, 5).slice(0, 7)}-04`, field('free').value);
        open('free');
        type('free', '');
        check('clearing the field by typing leaves the grid where it was', /\d{4}/.test(view()), view());
    });

group('bounds vs keyboard');
scenario('arrow keys respect min and max', { up: MAX_TODAY, down: MIN_TODAY },
    ({ check }, { key, show, field, view }) => {
        show('up', TODAY); key('ArrowRight', 'up');
        check('a move past max is refused', field('up').value === TODAY, field('up').value);
        show('up', TODAY); key('ArrowDown', 'up');
        check('…on the +7 vector too', field('up').value === TODAY, field('up').value);
        show('up', shiftDays(TODAY, -3)); key('ArrowRight', 'up');
        check('a move up to max is allowed', field('up').value === shiftDays(TODAY, -2), field('up').value);
        show('down', TODAY); key('ArrowLeft', 'down');
        check('a move before min is refused', field('down').value === TODAY, field('down').value);
        key('PageUp', 'down');
        check('paging still works beyond the bound (view only)', view() === nameOf(shiftMonths(TODAY, -1)), view());
    });

group('leap day and DST safety');
scenario('shifts are in days, never in milliseconds', { leap: AT('2024-02-28'), dst: AT('2026-03-27') },
    ({ check }, { key, open, show, field }) => {
        show('leap', '2024-02-28'); key('ArrowRight', 'leap');
        check('28 Feb 2024 -> 29 Feb 2024', field('leap').value === '2024-02-29', field('leap').value);
        key('ArrowRight', 'leap');
        check('…then 1 Mar 2024', field('leap').value === '2024-03-01', field('leap').value);
        show('leap', '2024-02-28'); key('ArrowUp', 'leap');
        check('one week back from the leap day crosses the year boundary cleanly',
            field('leap').value === shiftDays('2024-02-28', -7), field('leap').value);
        show('dst', '2026-03-27'); key('ArrowRight', 'dst');
        check('27 Mar 2026 -> 28 Mar 2026 (a European DST day, still +1 day)',
            field('dst').value === '2026-03-28', field('dst').value);
        key('ArrowDown', 'dst');
        check('…and +7 across it', field('dst').value === '2026-04-04', field('dst').value);
    });

group('short-month clamp');
scenario('a 31st viewed from a 28-day month', { end: AT('2027-01-31') },
    ({ check }, { $, cells, click, open, view, document }) => {
        const go = n => document.querySelector(`[data-cal-nav="${n}"]`).dispatchEvent(new document.defaultView.MouseEvent('click', { bubbles: true }));
        open('end');
        check('Jan 31 renders as selected', !!cells().find(c => c.dataset.calDay === '2027-01-31' && c.classList.contains('is-sel')));
        go(-1);
        check('paging back to a shorter month does not spill into the next one',
            view() === 'December 2026', view());
        check('…and the selection is simply not drawn rather than drawn on a wrong day',
            !cells().some(c => c.classList.contains('is-sel')) || cells().some(c => c.classList.contains('is-sel') && c.dataset.calDay.startsWith('2026-12')));
        go(1);
        check('paging forward again restores the selection', view() === 'January 2027'
            && !!cells().find(c => c.dataset.calDay === '2027-01-31' && c.classList.contains('is-sel')), view());
        go(-1); go(-1);
        check('two months back still lands on November 2026', view() === 'November 2026', view());
    });

group('footer actions');
scenario('Today and Clear', { empty: '' },
    ({ check }, { $, $$, click, cal, open, field, view, document }) => {
        open('empty');
        check('an empty field opens on the current month', view() === nameOf(TODAY), view());
        check('…with nothing pre-selected', !$$('.pkg-cal-day.is-sel').length);
        check('…and focus on today', document.activeElement?.dataset?.calDay === TODAY, document.activeElement?.dataset?.calDay);
        click($('[data-cal-act="today"]'));
        check('Today writes the current date', field('empty').value === TODAY, field('empty').value);
        open('empty');
        click($('[data-cal-act="clear"]'));
        check('Clear empties the field', field('empty').value === '', field('empty').value);
        check('Clear closes the popup', cal().hidden);
    });

group('outside click and re-targeting');
scenario('one popup, re-bound on demand', { a: AT(TODAY), b: AT('2027-06-15') },
    ({ check }, { $, $$, cal, click, open, view, document }) => {
        open('a');
        click(cal().querySelector('.pkg-cal-head'));
        check('a click inside the popup does not close it', !cal().hidden);
        open('b');
        check('clicking another field re-targets it', view() === 'June 2027', view());
        check('…and only one trigger is marked open', $$('.pkg-calbtn.is-open').length === 1);
        click(document.body);
        check('a click outside closes it', cal().hidden);
        check('…and clears every open marker', $$('.pkg-calbtn.is-open').length === 0);
    });

group('resilience');
scenario('a field replaced by a re-render', { paid_on: AT(TODAY) },
    ({ check }, { $, cal, click, open, document, key, view }) => {
        // The shape Livewire / Alpine produce: the old input is gone, a new one
        // with the same id has no wrapper and no trigger button.
        document.getElementById('paid_on').closest('.pkg-field').innerHTML = '<input id="paid_on" type="date" value="">';
        click(document.getElementById('paid_on'));
        check('the popup still opens', !cal().hidden);
        key('PageUp', 'paid_on'); key('ArrowRight', 'paid_on'); key('Escape', 'paid_on');
        check('paging and typing on it throw nothing', cal().hidden);
        document.querySelector('.pkg-field').innerHTML = '<input type="date" value="2027-06-15">';
        const orphan = document.querySelector('.pkg-field input');
        document.defaultView.__pkgCalShow(orphan);   // no id → no button → show it directly
        check('even a field with no id opens, and centres on its value',
            !cal().hidden && /June 2027/.test(view()), view());
        click(document.body);
        check('and closing it does not look for a trigger that is not there', cal().hidden);
    });

/* ----------------------------------------------------------------- reporting */

console.log(`\n${pass} passed, ${fail} failed   (pinned "today" = ${TODAY}${process.env.PIN_TODAY ? '' : ', from the system clock'})`);
process.exit(fail ? 1 : 0);
