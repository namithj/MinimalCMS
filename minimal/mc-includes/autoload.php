<?php

/**
 * PSR-4-style autoloader for MC_ classes.
 *
 * Maps class names like MC_Hooks to mc-includes/classes/class-mc-hooks.php.
 *
 * @package MinimalCMS
 * @since   {version}
 */

spl_autoload_register(function (string $class): void {

	// Only handle MC_ prefixed classes.
	if (strncmp($class, 'MC_', 3) !== 0) {
		return;
	}

	$file = __DIR__ . '/classes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';

	if (is_file($file)) {
		require_once $file;
	}
});
