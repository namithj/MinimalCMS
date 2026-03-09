#!/usr/bin/env node

/**
 * MinimalCMS — Project Setup
 *
 * One-time bootstrapper for new developers. Builds the Docker image,
 * installs PHP dependencies, compiles assets, and seeds the config file.
 *
 * Usage:
 *   npm run setup
 *
 * @package MinimalCMS
 */

const fs   = require('fs');
const path = require('path');
const { execSync } = require('child_process');

const ROOT = path.resolve(__dirname, '..');

// ── Helpers ────────────────────────────────────────────────────────────────

function run(label, cmd) {
	console.log(`\n\x1b[36m▸ ${label}\x1b[0m`);
	execSync(cmd, { cwd: ROOT, stdio: 'inherit' });
}

// ── 1. Environment check ──────────────────────────────────────────────────

run('Checking prerequisites', 'node scripts/check-env.js');

// ── 2. Build Docker image ─────────────────────────────────────────────────

run('Building Docker image', 'docker compose build');

// ── 3. Install PHP dependencies via Composer ──────────────────────────────

run('Installing PHP dependencies (Composer)', 'docker compose run --rm app composer install');

// ── 4. Build front-end assets (SCSS + vendor copy) ────────────────────────

run('Building front-end assets', 'npm run build');

// ── 5. Seed config.json from sample ───────────────────────────────────────

const configPath = path.join(ROOT, 'config.json');
const samplePath = path.join(ROOT, 'config.sample.json');

if (!fs.existsSync(configPath) && fs.existsSync(samplePath)) {
	fs.copyFileSync(samplePath, configPath);
	console.log('\n\x1b[36m▸ Created config.json from config.sample.json\x1b[0m');
} else if (fs.existsSync(configPath)) {
	console.log('\n\x1b[33m▸ config.json already exists — skipping.\x1b[0m');
}

// ── Done ───────────────────────────────────────────────────────────────────

const port = process.env.MC_PORT || '8080';

console.log(`
\x1b[32m✓ Setup complete!\x1b[0m

  Start developing:
    npm run dev

  The CMS will be available at:
    http://localhost:${port}

  The setup wizard will run on first visit.
`);
