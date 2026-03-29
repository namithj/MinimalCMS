#!/usr/bin/env php
<?php

/**
 * MinimalCMS Recovery Restore Helper
 *
 * Restores key files from an encrypted setup backup bundle.
 *
 * Usage:
 *   php scripts/recovery-restore.php --bundle=/absolute/path/to/minimalcms-key-backup.json
 *   php scripts/recovery-restore.php --bundle=/path/backup.json --passphrase='strong passphrase'
 *   php scripts/recovery-restore.php --bundle=/path/backup.json --abspath=/var/www/site
 *
 * @package MinimalCMS
 * @since   {version}
 */

if ('cli' !== PHP_SAPI && 'phpdbg' !== PHP_SAPI) {
	fwrite(STDERR, "This script must be run from the CLI.\n");
	exit(1);
}

$abspath = dirname(__DIR__) . '/';
define('MC_ABSPATH', $abspath);

$composer_autoload = MC_ABSPATH . 'mc-includes/vendor/autoload.php';
if (is_file($composer_autoload)) {
	require_once $composer_autoload;
}

require_once MC_ABSPATH . 'mc-includes/autoload.php';

$options = getopt('', array('bundle:', 'passphrase::', 'abspath::', 'help::'));

if (isset($options['help'])) {
	echo "MinimalCMS recovery restore helper\n\n";
	echo "Required:\n";
	echo "  --bundle=/absolute/path/to/minimalcms-key-backup.json\n\n";
	echo "Optional:\n";
	echo "  --passphrase='your passphrase'\n";
	echo "  --abspath=/absolute/path/to/site/root\n";
	exit(0);
}

$bundle_path = (string) ($options['bundle'] ?? '');
if ('' === $bundle_path) {
	fwrite(STDERR, "Missing required option: --bundle\n");
	exit(1);
}

if (!is_file($bundle_path) || !is_readable($bundle_path)) {
	fwrite(STDERR, "Backup bundle file is missing or unreadable.\n");
	exit(1);
}

$target_abspath = (string) ($options['abspath'] ?? MC_ABSPATH);
$target_abspath = rtrim($target_abspath, '/') . '/';

if (!is_dir($target_abspath . 'mc-data/')) {
	fwrite(STDERR, "Target path does not look like a MinimalCMS root (missing mc-data/).\n");
	exit(1);
}

$passphrase = (string) ($options['passphrase'] ?? '');
if ('' === $passphrase) {
	$passphrase = mc_restore_prompt_hidden('Enter backup passphrase: ');
}

if ('' === trim($passphrase)) {
	fwrite(STDERR, "Passphrase is required.\n");
	exit(1);
}

$bundle_content = file_get_contents($bundle_path);
if (false === $bundle_content) {
	fwrite(STDERR, "Could not read backup bundle file.\n");
	exit(1);
}

$result = MC_Setup::restore_backup_bundle($target_abspath, $bundle_content, $passphrase);

if ($result instanceof MC_Error) {
	fwrite(STDERR, "Restore failed: " . $result->get_error_code() . " - " . $result->get_error_message() . "\n");
	exit(1);
}

echo "Recovery restore completed.\n";
echo "Rehydrated files:\n";
echo "- " . $target_abspath . 'mc-data/' . MC_Keystore::WEBROOT_FILE . "\n";
echo "- " . $target_abspath . 'mc-data/' . MC_Keystore::KEYS_FILE . "\n";

exit(0);

/**
 * Prompt for sensitive input without echoing to terminal.
 *
 * @since {version}
 *
 * @param string $prompt Prompt text.
 * @return string
 */
function mc_restore_prompt_hidden(string $prompt): string
{
	fwrite(STDOUT, $prompt);

	$stty_mode = shell_exec('stty -g');
	if (is_string($stty_mode) && '' !== trim($stty_mode)) {
		shell_exec('stty -echo');
	}

	$line = fgets(STDIN);

	if (is_string($stty_mode) && '' !== trim($stty_mode)) {
		shell_exec('stty ' . trim($stty_mode));
	}

	fwrite(STDOUT, PHP_EOL);

	return false === $line ? '' : trim($line);
}
