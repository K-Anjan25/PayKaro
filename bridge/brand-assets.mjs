/**
 * PayKaro — regenerate the brand assets that are *files*: the favicon set and the
 * social share card.
 *
 * BRAND_PLAN §3.1 and §4.3 record both as missing: `public/favicon.ico` was a
 * 0-byte file (every browser tab blank) and there was no `og:` image at all, so a
 * link dropped into WhatsApp — the channel this market actually uses — rendered
 * with nothing.
 *
 * The app itself has no build step, and that stays true: these are committed
 * assets, rendered once and reviewable. This script exists so they can be
 * *re-rendered* when the tokens or the wordmark change, rather than being
 * mystery binaries nobody can reproduce.
 *
 * It needs ImageMagick (`convert`) and Plus Jakarta Sans — the app's own brand
 * font, which the layouts load from Google Fonts. Google Fonts is not reachable
 * from the sandbox, but the same files ship in an npm package:
 *
 *   cd /tmp && npm pack @expo-google-fonts/plus-jakarta-sans
 *   tar xzf expo-google-fonts-plus-jakarta-sans-*.tgz
 *   FONT_DIR=/tmp/package node bridge/brand-assets.mjs
 *
 * outputs: public/favicon.ico            (16/32/48, replaces the 0-byte file)
 *          public/assets/img/icon-32.png
 *          public/assets/img/icon-180.png  (apple-touch-icon)
 *          public/assets/img/icon-512.png
 *          public/assets/img/og-default.png (1200×630, the share card)
 *
 * env: FONT_DIR (a directory tree containing PlusJakartaSans_*ExtraBold.ttf and
 *      …_SemiBold.ttf) · CONVERT (default `convert`)
 */

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const CONVERT = process.env.CONVERT || 'convert';
const ROOT = process.env.ROOT || path.resolve(import.meta.dirname, '..');
const IMG = path.join(ROOT, 'public', 'assets', 'img');

/* Obsidian Flow, taken from :root in public/assets/app.css. Copied here because a
   generator that reads the stylesheet would still have to parse CSS to find them;
   the icon is a brand asset reviewed by eye, not a theme-aware component. */
const INK = '#0b132b';          // --n-ink: obsidian, the tile
const PAPER = '#eaf1ff';        // ink as it reads on obsidian (html.dark --n-ink)
const AZZURRO = '#60a5fa';      // the wordmark's second syllable on dark
const SOFT = '#cbd5e1';
const MUTE = '#94a3b8';

const FONT_DIR = process.env.FONT_DIR || '/tmp/package';
const needed = {
	extraBold: 'PlusJakartaSans_800ExtraBold.ttf',
	semiBold: 'PlusJakartaSans_600SemiBold.ttf',
};

function findFont(file) {
	const direct = path.join(FONT_DIR, file);
	if (fs.existsSync(direct)) return direct;

	const walk = (dir) => {
		for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
			const full = path.join(dir, entry.name);
			if (entry.isDirectory()) {
				const hit = walk(full);
				if (hit) return hit;
			} else if (entry.name === file) return full;
		}
		return null;
	};

	const hit = fs.existsSync(FONT_DIR) ? walk(FONT_DIR) : null;
	if (hit) return hit;

	console.error(`[brand] ${file} not found under FONT_DIR=${FONT_DIR}`);
	console.error('[brand] fetch it:  cd /tmp && npm pack @expo-google-fonts/plus-jakarta-sans && tar xzf *.tgz');
	process.exit(2);
}

const fonts = {
	extraBold: findFont(needed.extraBold),
	semiBold: findFont(needed.semiBold),
};

const run = (args) => execFileSync(CONVERT, args, { stdio: ['ignore', 'pipe', 'pipe'] });
const textWidth = (font, size, text) =>
	Number(run(['-font', font, '-pointsize', String(size), 'label:' + text, '-format', '%w', 'info:']).toString().trim());

/* --------------------------------------------------------------- share card */

const W = 1200, H = 630, M = 96;

function shareCard(out) {
	const word = 'Pay';
	const size = 108;

	/* Two-tone on one baseline, so the second syllable starts where the first
	   ends — measured, not guessed, because a renamed deployment changes it. */
	const wordWidth = textWidth(fonts.extraBold, size, word);
	const tailWidth = textWidth(fonts.extraBold, size, 'Karo');

	const lines = [
		{ text: 'MSME invoice & receivables tracker', size: 44, font: fonts.semiBold, fill: SOFT, y: H - 250 },
		{ text: 'Raised · accepted · financed · settled — every invoice on one pipeline.', size: 29, font: fonts.semiBold, fill: MUTE, y: H - 190 },
		{ text: 'Evidence checklists, statutory interest and a claim packet that stands.', size: 29, font: fonts.semiBold, fill: MUTE, y: H - 148 },
		{ text: 'MSMED Act 2006 · Section 16 interest · TReDS liquidity', size: 26, font: fonts.extraBold, fill: AZZURRO, y: H - 74 },
	];

	/* A line that runs past the right margin is the one failure a share card
	   cannot recover from — it is cropped by whoever renders it. */
	for (const line of lines) {
		const width = textWidth(line.font, line.size, line.text);
		if (M + width > W - M) {
			throw new Error(`"${line.text}" is ${width}px, wider than the ${W - 2 * M}px text column`);
		}
	}

	const args = ['-size', `${W}x${H}`, `xc:${INK}`];

	for (const line of [...lines].sort((a, b) => a.y - b.y)) {
		args.push('-font', line.font, '-pointsize', String(line.size), '-fill', line.fill, '-annotate', `+${M}+${line.y}`, line.text);
	}

	run([
		// Wordmark last, and again as its own pair of draws: it is the one thing on
		// the card that must be optically right.
		...args.slice(0, 3),
		'-font', fonts.extraBold, '-pointsize', String(size), '-fill', PAPER, '-annotate', `+${M}+${H - 360}`, word,
		'-fill', AZZURRO, '-annotate', `+${M + wordWidth}+${H - 360}`, 'Karo',
		...args.slice(3),
		// the accent rule the wordmark's second syllable is drawn in
		'-fill', AZZURRO, '-draw', `rectangle ${M},${H - 108} ${M + 96},${H - 104}`,
		'-strip', out,
	]);

	// Guard against a renderer that silently drops the font and draws nothing.
	if (fs.statSync(out).size < 4096) throw new Error(`${out} looks empty — did the font load?`);
}

/* -------------------------------------------------------------------- icons */

function icon(out, size) {
	const point = Math.round(size * 0.66);
	const barW = Math.round(size * 0.34);
	const barH = Math.max(1, Math.round(size * 0.055));

	run([
		'-size', `${size}x${size}`, `xc:${INK}`,
		'-font', fonts.extraBold, '-pointsize', String(point), '-fill', PAPER,
		'-gravity', 'north', '-annotate', `+0+${Math.round(size * 0.10)}`, 'P',
		'-fill', AZZURRO,
		'-draw', `rectangle ${Math.round((size - barW) / 2)},${size - Math.round(size * 0.12)} ${Math.round((size + barW) / 2)},${size - Math.round(size * 0.12) + barH}`,
		'-strip', out,
	]);
}

fs.mkdirSync(IMG, { recursive: true });

shareCard(path.join(IMG, 'og-default.png'));
icon(path.join(IMG, 'icon-512.png'), 512);
icon(path.join(IMG, 'icon-180.png'), 180);
icon(path.join(IMG, 'icon-32.png'), 32);

// One ICO carrying every size a browser might ask for; the committed file used to
// be 0 bytes, which is exactly the failure this replaces.
run([
	path.join(IMG, 'icon-512.png'),
	'-define', 'icon:auto-resize=48,32,16',
	path.join(ROOT, 'public', 'favicon.ico'),
]);

for (const file of ['og-default.png', 'icon-512.png', 'icon-180.png', 'icon-32.png', '../..' + '/favicon.ico']) {
	const target = path.resolve(IMG, file);
	console.log(`[brand] ${path.relative(ROOT, target)}  ${fs.statSync(target).size} bytes`);
}
