#!/usr/bin/env node

/**
 * MinimalCMS — Version Updater
 *
 * Updates the version string across all relevant files in the codebase.
 *
 * Usage:
 *   node scripts/update-version.js <new-version>
 *   npm run version -- 0.1.0
 *
 * Files updated:
 *   - package.json
 *   - composer.json
 *   - mc-includes/version.php
 *   - README.md
 *   - docs/index.html
 *
 * package-lock.json is regenerated automatically via `npm install --package-lock-only`.
 */

const fs   = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');

// ── Validate input ─────────────────────────────────────────────────────────

const newVersion = process.argv[2];

if (!newVersion) {
	console.error('Usage: node scripts/update-version.js <new-version>');
	console.error('Example: node scripts/update-version.js 0.1.0');
	process.exit(1);
}

if (!/^\d+\.\d+\.\d+(-[\w.]+)?$/.test(newVersion)) {
	console.error(`Invalid semver format: "${newVersion}"`);
	console.error('Expected: MAJOR.MINOR.PATCH or MAJOR.MINOR.PATCH-prerelease');
	process.exit(1);
}

// ── Detect current version from package.json ───────────────────────────────

const pkgPath = path.join(ROOT, 'package.json');
const pkg     = JSON.parse(fs.readFileSync(pkgPath, 'utf8'));
const oldVersion = pkg.version;

if (oldVersion === newVersion) {
	console.log(`Version is already ${newVersion}. Nothing to do.`);
	process.exit(0);
}

console.log(`Updating version: ${oldVersion} → ${newVersion}\n`);

// ── Helpers ────────────────────────────────────────────────────────────────

function replaceInFile(relPath, replacements) {
	const filePath = path.join(ROOT, relPath);

	if (!fs.existsSync(filePath)) {
		console.warn(`  ⚠  Skipped (not found): ${relPath}`);
		return;
	}

	let content = fs.readFileSync(filePath, 'utf8');
	let changed = false;

	for (const [search, replace] of replacements) {
		const updated = content.replace(search, replace);
		if (updated !== content) {
			content = updated;
			changed = true;
		}
	}

	if (changed) {
		fs.writeFileSync(filePath, content, 'utf8');
		console.log(`  ✓  ${relPath}`);
	} else {
		console.warn(`  ⚠  No changes: ${relPath}`);
	}
}

function escapeForRegex(str) {
	return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

const oldEsc = escapeForRegex(oldVersion);

// ── Apply replacements ────────────────────────────────────────────────────

// package.json — "version": "x.y.z"
replaceInFile('package.json', [
	[new RegExp(`("version":\\s*")${oldEsc}(")`), `$1${newVersion}$2`],
]);

// composer.json — "version": "x.y.z"
replaceInFile('composer.json', [
	[new RegExp(`("version":\\s*")${oldEsc}(")`), `$1${newVersion}$2`],
]);

// mc-includes/version.php — define('MC_VERSION', 'x.y.z');
replaceInFile('mc-includes/version.php', [
	[
		new RegExp(`(define\\('MC_VERSION',\\s*')${oldEsc}('\\))`),
		`$1${newVersion}$2`,
	],
]);

// README.md — badge URL version-x.y.z
replaceInFile('README.md', [
	[new RegExp(`(version-)${oldEsc}(-blue)`, 'g'), `$1${newVersion}$2`],
]);

// docs/index.html — multiple version references
replaceInFile('docs/index.html', [
	// Hero badge: ◆ vX.Y.Z
	[new RegExp(`(v)${oldEsc}`, 'g'), `$1${newVersion}`],
	// Shield badge URL and alt text
	[new RegExp(`(version-)${oldEsc}`, 'g'), `$1${newVersion}`],
	[new RegExp(`(Version )${oldEsc}`, 'g'), `$1${newVersion}`],
]);

// ── Sync package-lock.json ─────────────────────────────────────────────────

console.log('');
try {
	execSync('npm install --package-lock-only', { cwd: ROOT, stdio: 'ignore' });
	console.log('  ✓  package-lock.json');
} catch {
	console.warn('  ⚠  Could not update package-lock.json (run `npm install` manually)');
}

console.log(`\nDone — version is now ${newVersion}`);
