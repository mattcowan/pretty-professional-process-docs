#!/usr/bin/env node
/**
 * export-pdf.mjs — tagged (accessible) PDF export for reports.
 * Contract: report-design.md
 *
 * Usage:
 *   node export-pdf.mjs <input.html | url> <output.pdf> [--login user:app_password]
 *
 * Renders via Chromium and exports with page.pdf({ tagged: true }) so the
 * semantic HTML becomes the PDF tag structure.
 *
 * Two gates, and both matter. Chromium emits /Marked true + /StructTreeRoot for
 * ANY html it renders, so the tag check alone would pass on a login screen or a
 * 404. So the source page is verified first (HTTP status, not-a-login-page, and
 * the report-design shape contract), and only then is the tag structure checked.
 *
 * Exit codes: 1 output not tagged · 2 usage · 3 playwright missing
 *             4 bad HTTP status · 5 landed on a login page · 6 not a report
 *
 * Dependency: playwright OR playwright-core (with a system Chrome/Edge).
 * Resolved from: cwd node_modules → this directory → global npm root.
 * Install hint: npm i -g playwright-core   (uses installed Edge/Chrome)
 */

import { createRequire } from 'node:module';
import { execSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { pathToFileURL, fileURLToPath } from 'node:url';

const args = process.argv.slice(2);
const loginIdx = args.indexOf('--login');
let login = null;
if (loginIdx !== -1) {
	login = args.splice(loginIdx, 2)[1];
	if (!login || login.startsWith('--') || !login.includes(':')) {
		console.error('Error: --login requires a value in the form user:app_password');
		process.exit(2);
	}
}
const [input, output] = args;

if (!input || !output) {
	console.error('Usage: node export-pdf.mjs <input.html|url> <output.pdf> [--login user:app_password]');
	process.exit(2);
}

/** Find playwright or playwright-core wherever it lives. */
function loadPlaywright() {
	const bases = [
		resolve(process.cwd(), 'noop.js'),
		resolve(dirname(fileURLToPath(import.meta.url)), 'noop.js'),
	];
	try {
		const globalRoot = execSync('npm root -g', { encoding: 'utf8' }).trim();
		bases.push(resolve(globalRoot, 'noop.js'));
	} catch {
		/* npm not on PATH; skip global resolution */
	}
	for (const base of bases) {
		const req = createRequire(base);
		for (const name of ['playwright', 'playwright-core']) {
			try {
				return { pw: req(name), name };
			} catch {
				/* try next */
			}
		}
	}
	console.error(
		'Could not find playwright or playwright-core.\n' +
			'Fix: npm i -g playwright-core   (uses your installed Edge/Chrome)\n' +
			'or run from a project that has playwright installed.'
	);
	process.exit(3);
}

const { pw, name: pkgName } = loadPlaywright();

async function launch() {
	// Full playwright may have its own chromium; playwright-core needs a
	// system channel. Try bundled first, then Edge (always on Win11), then Chrome.
	const attempts =
		pkgName === 'playwright'
			? [{}, { channel: 'msedge' }, { channel: 'chrome' }]
			: [{ channel: 'msedge' }, { channel: 'chrome' }];
	let lastErr;
	for (const opts of attempts) {
		try {
			return await pw.chromium.launch(opts);
		} catch (err) {
			lastErr = err;
		}
	}
	throw lastErr;
}

const url = /^https?:\/\//i.test(input)
	? input
	: pathToFileURL(resolve(input)).href;

if (!/^https?:\/\//i.test(input) && !existsSync(resolve(input))) {
	console.error(`Input file not found: ${input}`);
	process.exit(2);
}

const browser = await launch();
try {
	const contextOpts = {};
	if (login) {
		const [user, ...rest] = login.split(':');
		// send: 'always' is REQUIRED, not a nicety. The default is 'unauthorized',
		// which only attaches the Authorization header after a 401 + WWW-Authenticate
		// challenge. WordPress does not challenge a front-end page — it redirects to
		// wp-login.php — so with the default the header is never sent, the exporter
		// renders the login screen, and (because Chromium tags any HTML it renders)
		// the tagged-PDF check below would happily pass on it.
		contextOpts.httpCredentials = {
			username: user,
			password: rest.join(':'),
			send: 'always',
		};
	}
	// Local dev sites use self-signed certs
	contextOpts.ignoreHTTPSErrors = true;

	const context = await browser.newContext(contextOpts);
	const page = await context.newPage();
	await page.emulateMedia({ media: 'print' });
	const response = await page.goto(url, { waitUntil: 'networkidle' });

	// A PDF of an error page is still a valid tagged PDF. Verify we rendered the
	// document we were asked for, BEFORE spending the export on it.
	if (response && !response.ok()) {
		await browser.close();
		console.error(
			`FAIL: ${url} returned HTTP ${response.status()} ${response.statusText()}.\n` +
				'Nothing was exported. Check the URL, and if the report requires login, ' +
				'pass --login user:app_password.'
		);
		process.exit(4);
	}

	const finalUrl = page.url();
	if (/wp-login\.php|\/wp-admin\/|\/login\/?$/i.test(finalUrl)) {
		await browser.close();
		console.error(
			`FAIL: ended up at a login page (${finalUrl}).\n` +
				'The credentials were rejected or not supplied. Check PPPD_USER / ' +
				'PPPD_APP_PASS and that the user can view this report.'
		);
		process.exit(5);
	}

	// Assert the report-design contract: exactly one <main>, at least one <h1>,
	// and no login form. This is what distinguishes a report from any other page
	// the server might have handed us (403 notice, "restricted" template, 404).
	const shape = await page.evaluate(() => ({
		mains: document.querySelectorAll('main').length,
		h1s: document.querySelectorAll('h1').length,
		loginForm: !!document.querySelector('#loginform, form[name="loginform"]'),
		title: document.title,
		bodyChars: (document.body?.innerText || '').trim().length,
	}));

	if (shape.loginForm || shape.mains !== 1 || shape.h1s < 1 || shape.bodyChars < 200) {
		await browser.close();
		console.error(
			`FAIL: ${finalUrl} does not look like a report.\n` +
				`  <main> elements: ${shape.mains} (expected exactly 1)\n` +
				`  <h1> elements:   ${shape.h1s} (expected at least 1)\n` +
				`  login form:      ${shape.loginForm}\n` +
				`  body text:       ${shape.bodyChars} chars\n` +
				`  page title:      ${JSON.stringify(shape.title)}\n` +
				'Nothing was exported. A tagged PDF of an error or login page is still ' +
				'a valid tagged PDF, so this check is what stops one being delivered as ' +
				'an "accessible PDF".'
		);
		process.exit(6);
	}

	const pdfOpts = {
		path: resolve(output),
		format: 'A4',
		preferCSSPageSize: true,
		printBackground: false,
		tagged: true,
		outline: true,
	};
	try {
		await page.pdf(pdfOpts);
	} catch (err) {
		if (/outline/i.test(String(err))) {
			// Older playwright without outline support — tagged is the must-have.
			delete pdfOpts.outline;
			await page.pdf(pdfOpts);
		} else {
			throw err;
		}
	}
} finally {
	await browser.close();
}

// Verify tag structure actually made it into the file.
const bytes = readFileSync(resolve(output), 'latin1');
const marked = bytes.includes('/Marked true');
const structTree = bytes.includes('/StructTreeRoot');
if (!marked || !structTree) {
	console.error(
		`FAIL: ${output} is not a tagged PDF ` +
			`(Marked: ${marked}, StructTreeRoot: ${structTree}). ` +
			'Check that page.pdf({ tagged: true }) is supported by this Chromium/playwright version.'
	);
	process.exit(1);
}
console.log(
	`OK: tagged PDF written to ${resolve(output)} ` +
		'(source page verified as a report; Marked + StructTreeRoot present)'
);
