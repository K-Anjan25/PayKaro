{{--
    Calendar popups for every `<input type="date">` in the app.

    Deliberately one self-contained include rather than a Vite/asset pipeline:
    PayKaro ships zero-build CSS in public/assets/app.css, and a popup with its
    own styles stays honest in exactly that arrangement. The popup is a single
    node reused by all fields (there are two or three per page at most), and the
    script is idempotent behind `window.__pkgDatepicker`, so it is safe to
    include from every layout.

    Why a custom popup when Chrome and Firefox have one: `showPicker()` cannot be
    styled, is absent on older Safari/Firefox, and opens a UI unrelated to the
    workspace. This popup reads the input's own `YYYY-MM-DD` value and writes it
    back, so it never becomes a second source of truth — and with JS off, the
    native field still works.
--}}

<div class="pkg-cal" id="pkg-cal" role="dialog" aria-modal="false" aria-label="Choose a date" hidden>
    <div class="pkg-cal-head">
        <button class="pkg-cal-nav" type="button" data-cal-nav="-1" aria-label="Previous month">&#8249;</button>
        <p class="pkg-cal-mon" id="pkg-cal-mon" aria-live="polite">&nbsp;</p>
        <button class="pkg-cal-nav" type="button" data-cal-nav="1" aria-label="Next month">&#8250;</button>
    </div>
    <div class="pkg-cal-dows">
        <span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span>
    </div>
    <div class="pkg-cal-grid" id="pkg-cal-grid" onkeydown="return window.__pkgCalKeys(event)"></div>
    <div class="pkg-cal-foot">
        <button class="pkg-cal-today" type="button" data-cal-act="today">Today</button>
        <button class="pkg-cal-clear" type="button" data-cal-act="clear">Clear</button>
    </div>
</div>

<style>
    /* Hide the browser's own indicator: the field gets a themed trigger instead. */
    input[type=date]::-webkit-calendar-picker-indicator,
    input[type=date]::-webkit-inner-spin-button { opacity:0; pointer-events:none; }
    input[type=date] { cursor:pointer; }

    .pkg-calwrap { display:flex; gap:.4rem; align-items:center; }
    .pkg-calwrap .pkg-input { flex:1 1 auto; min-width:0; }

    .pkg-calbtn { flex:0 0 auto; width:2.6rem; height:2.6rem; display:inline-flex; align-items:center;
        justify-content:center; border:1px solid var(--n-line); border-radius:10px; background:var(--n-paper);
        color:var(--n-ink-soft); cursor:pointer; transition:all .15s; }
    .pkg-calbtn:hover { border-color:var(--n-blue); color:var(--n-blue); background:var(--n-blue-soft); }
    .pkg-calbtn:focus-visible { outline:2px solid var(--n-blue); outline-offset:2px; }
    .pkg-calbtn.is-open { border-color:var(--n-blue); color:var(--n-blue); background:var(--n-blue-soft); }

    .pkg-cal { position:absolute; z-index:60; width:17.5rem; max-width:calc(100vw - 1.5rem);
        background:var(--n-paper); border:1px solid var(--n-line); border-radius:14px; box-shadow:0 18px 42px rgba(15,29,46,.22);
        padding:.7rem .75rem .6rem; font-family:var(--n-font); color:var(--n-ink); }
    .pkg-cal[hidden] { display:none; }

    .pkg-cal-head { display:flex; align-items:center; justify-content:space-between; gap:.4rem; margin-bottom:.45rem; }
    .pkg-cal-mon { margin:0; font-family:var(--n-display); font-size:.95rem; font-weight:600; letter-spacing:.01em; }
    .pkg-cal-nav { width:1.85rem; height:1.85rem; border:1px solid var(--n-line); border-radius:8px;
        background:transparent; color:var(--n-ink-soft); font-size:1rem; line-height:1; cursor:pointer; }
    .pkg-cal-nav:hover { background:var(--n-blue-soft); color:var(--n-blue); border-color:var(--n-blue); }

    /* minmax(0,1fr): the popup is clamped to the viewport on a phone, and a bare 1fr
       track would refuse to shrink below the widest day label, pushing cells past the
       rounded border instead of compressing. */
    .pkg-cal-dows, .pkg-cal-grid { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:2px; }
    .pkg-cal-dows span { text-align:center; font-size:.64rem; font-weight:700; letter-spacing:.05em;
        text-transform:uppercase; color:var(--n-ink-mute); padding-bottom:.25rem; }

    .pkg-cal-day { aspect-ratio:1/1; min-height:1.85rem; border:0; border-radius:8px; background:transparent;
        color:var(--n-ink); font:inherit; font-size:.78rem; font-weight:600; cursor:pointer;
        font-variant-numeric:tabular-nums; }
    .pkg-cal-day:hover { background:var(--n-blue-soft); }
    .pkg-cal-day:focus-visible { outline:2px solid var(--n-blue); outline-offset:-2px; }
    .pkg-cal-day.is-out { color:var(--n-ink-mute); opacity:.45; }
    .pkg-cal-day.is-today { box-shadow:inset 0 0 0 1px var(--n-gold-strong); }
    .pkg-cal-day.is-sel { background:var(--n-blue); color:var(--n-paper); }
    .pkg-cal-day.is-sel.is-today { box-shadow:inset 0 0 0 1px var(--n-gold-soft); }
    .pkg-cal-day[disabled] { opacity:.25; cursor:not-allowed; }

    .pkg-cal-foot { display:flex; justify-content:space-between; gap:.4rem; margin-top:.5rem;
        padding-top:.5rem; border-top:1px solid var(--n-line-2); }
    .pkg-cal-foot button { border:0; background:transparent; color:var(--n-blue); font:inherit; font-size:.74rem;
        font-weight:700; cursor:pointer; padding:.15rem .35rem; border-radius:6px; }
    .pkg-cal-foot button:hover { background:var(--n-blue-soft); }
    .pkg-cal-foot .pkg-cal-clear { color:var(--n-ink-mute); }
    .pkg-cal-foot .pkg-cal-clear:hover { color:var(--n-coral); background:rgba(231,104,79,.1); }
</style>

<script>
(function () {
    if (window.__pkgDatepicker) { return; }
    window.__pkgDatepicker = true;

    var pop = document.getElementById('pkg-cal');
    var grid = document.getElementById('pkg-cal-grid');
    var label = document.getElementById('pkg-cal-mon');
    var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'];
    var open = null;            // the date input the popup is bound to
    var view = null;          // [year, month0] of the rendered grid
    var filledBy = null;      // the field our own last write focused, to swallow its echo click

    // All calendar maths is done on UTC day numbers. A local Date would let a
    // DST shift or a timezone boundary move a "2026-04-21" by a day, which is
    // exactly the class of bug the MSMED due window cannot tolerate.
    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(t) { return t[0] + '-' + pad(t[1] + 1) + '-' + pad(t[2]); }
    function parse(s) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || '');
        return m ? [ +m[1], +m[2] - 1, +m[3] ] : null;
    }
    // Everything downstream reads a date as a [year, month0, day] triple, so a
    // Date must be converted back rather than passed on: `ymd(new Date(...))`
    // reads `t[0]` off a Date and produces "undefined-NaN-undefined", which is
    // exactly what the leading (previous-month) cells used to render.
    function parts(ms) {
        var d = new Date(ms);
        return [d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()];
    }
    function shift(t, days) {
        return parts(Date.UTC(t[0], t[1], t[2] + days));
    }
    function today() {
        // A single Date.now() read: local getFullYear/Month/Date is the *user's*
        // calendar day, which is what a field like "paid on" means, and it makes
        // "today" one patchable point for bridge/datepicker-harness.mjs.
        var n = new Date(Date.now());
        return [n.getFullYear(), n.getMonth(), n.getDate()];
    }
    function firstDow(t) {  // 0 = Monday, matching the Mo Tu We Th Fr Sa Su header
        return (new Date(Date.UTC(t[0], t[1], 1)).getUTCDay() + 6) % 7;
    }

    function bounds() {
        return { min: parse(open.getAttribute('min')), max: parse(open.getAttribute('max')) };
    }
    function allowed(d, b) {
        if (b.min && d < ymd(b.min)) { return false; }
        if (b.max && d > ymd(b.max)) { return false; }
        return true;
    }

    function render() {
        if (!open || !view) { return; }
        var b = bounds();
        var anchor = [view[0], view[1], 1];
        var lead = firstDow(view);
        var html = '';

        for (var i = 0; i < lead; i++) {
            var prev = shift(anchor, i - lead);
            html += dayBtn(prev, true, b);
        }
        for (var d = 1; d <= 32; d++) {
            var t = [view[0], view[1], d];
            if (t[1] !== new Date(Date.UTC(t[0], t[1], d)).getUTCMonth()) { break; }
            html += dayBtn(t, false, b);
        }
        grid.innerHTML = html;
        label.textContent = MONTHS[view[1]] + ' ' + view[0];
    }

    function dayBtn(t, out, b) {
        var value = ymd(t);
        var sel = parse(open.value);
        var now = today();
        var cls = 'pkg-cal-day'
            + (out ? ' is-out' : '')
            + (t[0] === now[0] && t[1] === now[1] && t[2] === now[2] ? ' is-today' : '')
            + (sel && value === ymd(sel) ? ' is-sel' : '');
        return '<button class="' + cls + '" type="button" role="gridcell" aria-label="' + value
            + (t[0] === now[0] && t[1] === now[1] && t[2] === now[2] ? ' (today)' : '')
            + '" tabindex="-1" data-cal-day="' + value + '"'
            + (allowed(value, b) ? '' : ' disabled') + '>' + t[2] + '</button>';
    }

    function place() {
        var r = open.getBoundingClientRect();
        var h = pop.offsetHeight || 300;
        var top = r.bottom + 6;
        if (top + h > window.innerHeight - 8 && r.top - h - 6 > 8) {
            top = r.top - h - 6;      // flip above when the field sits near the bottom
        }
        // Re-measure after the max-width clamp may have narrowed the popup, and
        // if even that does not fit, sit 8px from the viewport edge rather than
        // letting place() push the whole page wider than the screen.
        var w = pop.offsetWidth;
        var left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8));
        pop.style.top = (top + window.scrollY) + 'px';
        pop.style.left = (left + window.scrollX) + 'px';
    }

    function openFor(input) {
        if (!input || !input.isConnected) { return; }
        open = input;
        pop.hidden = false;
        var v = parse(input.value) || today();
        // Paging to November and then typing a November date must not yank the
        // grid back to today's month: only follow the value when it sits outside
        // the month already on screen (the `input` listener below does the same).
        var showing = view && view[0] === v[0] && view[1] === v[1];
        view = showing ? view : [v[0], v[1], 1];
        render();
        place();
        var btn = trigger();
        if (btn) { btn.classList.add('is-open'); }
        (grid.querySelector('.pkg-cal-day.is-sel:not([disabled])')
            || grid.querySelector('.pkg-cal-day.is-today:not([disabled])')
            || grid.querySelector('.pkg-cal-day:not([disabled])'))?.focus();
    }

    function close(back) {
        if (!open) { return; }
        var input = open;
        open = null;
        view = null;          // never outlive the field it was built from
        pop.hidden = true;
        document.querySelectorAll('.pkg-calbtn.is-open').forEach(function (b) { b.classList.remove('is-open'); });
        if (back !== false && input.isConnected && document.activeElement !== input) { input.focus(); }
    }

    function trigger() {
        if (!open) { return null; }
        if (open.__pkgCalBtn) { return open.__pkgCalBtn; }
        // No id means no way to find the button; the calendar still works, it
        // just has no trigger to light up.
        if (!open.id) { return null; }
        return document.querySelector('.pkg-calbtn[data-cal-for="' + open.id + '"]');
    }

    function commit(value) {
        if (!open) { return; }
        var field = open;
        filledBy = field;
        field.value = value;
        // 'input' not 'change': the invoice form previews GST/total/due date from
        // oninput, and a picker that skipped it would show a stale due date.
        field.dispatchEvent(new Event('input', { bubbles: true }));
        field.dispatchEvent(new Event('change', { bubbles: true }));
        close();
    }

    function monthBy(delta) {
        if (!open || !view) { return; }
        var d = new Date(Date.UTC(view[0], view[1] + delta, 1));
        view = [d.getUTCFullYear(), d.getUTCMonth(), 1];
        render();
    }

    // Typing into the field while the popup is open should move the grid, exactly
    // as Chrome's native picker does: the value is the source of truth, the grid
    // is a view of it. Without this, paging to November then typing 2026-11-04
    // leaves you looking at a highlighted day in a month you are no longer in.
    document.addEventListener('input', function (e) {
        if (!open || e.target !== open) { return; }
        var v = parse(open.value);
        if (!v || (view && v[0] === view[0] && v[1] === view[1])) { return; }
        view = [v[0], v[1], 1];
        render();
    }, true);

    window.__pkgCalShow = function (input) { openFor(input); };
    window.__pkgCalKeys = function (e) {
        if (!open || !view) { return true; }
        // A field the user cleared (or that was never filled) has no selection to
        // move: keep the arrows for the grid and let the popup decide the month.
        if (!(parse(open.value) || today())) { return true; }
        var sel = parse(open.value) || today();
        var b = bounds();
        var jump = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };

        if (e.key === 'Escape') { if (pop.hidden) { return true; } e.preventDefault(); close(); return false; }
        if (e.key === 'Tab') { close(false); return true; }
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
            // Enter submits a <form>. Only take over when the popup is actually
            // open on this field; otherwise the keypress must reach the browser
            // so the rest of the form still behaves normally.
            if (pop.hidden) { return true; }
            e.preventDefault();
            var focused = document.activeElement;
            if (focused && focused.dataset && focused.dataset.calDay) { commit(focused.dataset.calDay); }
            else if (open.value !== '') { commit(ymd(sel)); }   // empty field: nothing to choose
            return false;
        }
        if (jump[e.key]) {
            e.preventDefault();
            var next = ymd(shift(sel, jump[e.key]));
            if (allowed(next, b)) { open.value = next; view = parse(next); render(); }
            return false;
        }
        if (e.key === 'PageUp' || e.key === 'PageDown') {
            e.preventDefault();
            monthBy(e.key === 'PageUp' ? -1 : 1);
            return false;
        }
        if (e.key === 'Home') { e.preventDefault(); view = today(); render(); return false; }
        return true;
    };

    pop.addEventListener('click', function (e) {
        if (!open) { return; }   // the node outlives every session (it lives on body)
        var day = e.target.closest('[data-cal-day]');
        if (day) {
            if (day.disabled) { return; }   // out-of-bounds days are unchoosable, not just greyed
            commit(day.dataset.calDay);
            return;
        }
        var nav = e.target.closest('[data-cal-nav]');
        if (nav) { monthBy(parseInt(nav.dataset.calNav, 10)); return; }
        var act = e.target.closest('[data-cal-act]');
        if (!act) { return; }
        if (act.dataset.calAct === 'today') { commit(ymd(today())); } else { commit(''); }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.pkg-calbtn');
        if (btn) {
            var input = document.getElementById(btn.dataset.calFor);
            if (!input) { return; }
            if (open === input) { close(false); return; }
            close(false);
            openFor(input);
            return;
        }
        var input = e.target.closest('input[type=date]');
        if (input) {
            // Swallow only the click that our own write produced: close() hands
            // focus back to this field, and a browser fires a click on the
            // focused control when the pointer is released over it. A counter or
            // timestamp would do, but "this exact field, still bound, still
            // focused" is the actual condition and survives a clock that jumps.
            if (filledBy === input && input === document.activeElement) { filledBy = null; return; }
            close(false);
            openFor(input);
            return;
        }
        if (open && !e.target.closest('.pkg-cal')) { close(false); }
    }, true);

    document.addEventListener('keydown', function (e) {
        if (!open) { return; }
        if (e.target === open) { window.__pkgCalKeys(e); }
    });

    window.addEventListener('resize', function () { if (open) { place(); } });
    window.addEventListener('scroll', function () { if (open) { place(); } }, true);

    function wire() {
        // Included from <head>, so the body may not exist yet on first parse.
        if (!document.body) {
            document.addEventListener('DOMContentLoaded', wire);
            return;
        }

        document.querySelectorAll('input[type=date]').forEach(function (input) {
            if (input.dataset.pkgCal === '1') { return; }
            input.dataset.pkgCal = '1';

            var wrap = document.createElement('div');
            wrap.className = 'pkg-calwrap';
            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(input);

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pkg-calbtn';
            btn.setAttribute('aria-label', 'Open calendar');
            btn.dataset.calFor = input.id;
            btn.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" '
                + 'stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/>'
                + '<path d="M8 3v4M16 3v4M3 11h18"/></svg>';
            wrap.appendChild(btn);
            input.__pkgCalBtn = btn;
        });

        // The popup lives at the end of <body> so nothing can clip it; keyboard
        // events reach it through the input, which keeps focus while it is open.
        document.body.appendChild(pop);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', wire);
    } else {
        wire();
    }
})();
</script>
