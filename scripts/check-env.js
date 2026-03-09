#!/usr/bin/env node

/**
 * MinimalCMS — Environment Checker
 *
 * Verifies that Docker and Docker Compose are available before starting
 * the dev server. Runs automatically via the `predev` and `preserve`
 * npm lifecycle hooks.
 *
 * @package MinimalCMS
 */

const { execSync } = require('child_process');

const checks = [
	{
		label: 'Docker CLI',
		cmd: 'docker --version',
		hint: 'Install Docker: https://docs.docker.com/get-docker/',
	},
	{
		label: 'Docker daemon',
		cmd: 'docker info',
		hint: 'Start Docker Desktop or the Docker daemon (`sudo systemctl start docker`).',
	},
	{
		label: 'Docker Compose',
		cmd: 'docker compose version',
		hint: 'Docker Compose v2 is bundled with Docker Desktop. For Linux: https://docs.docker.com/compose/install/',
	},
];

let failed = false;

for (const check of checks) {
	try {
		execSync(check.cmd, { stdio: 'pipe' });
	} catch {
		console.error(`\x1b[31m✗ ${check.label} not available.\x1b[0m`);
		console.error(`  ${check.hint}\n`);
		failed = true;
	}
}

if (failed) {
	console.error('\x1b[31mEnvironment check failed. Fix the issues above and try again.\x1b[0m');
	process.exit(1);
}

const port = process.env.MC_PORT || '8080';

console.log('\x1b[32m✓ Docker environment OK.\x1b[0m');
console.log(`\n\x1b[36m  MinimalCMS will be available at:\x1b[0m`);
console.log(`\x1b[1m  http://localhost:${port}\x1b[0m\n`);
