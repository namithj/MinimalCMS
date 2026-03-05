<?php
/**
 * MinimalCMS Hook System
 *
 * Provides the action and filter API that powers extensibility.
 * Actions are side-effect hooks; filters modify and return values.
 *
 * Inspired by the WordPress hook architecture but written from scratch.
 *
 * @package MinimalCMS
 * @since   1.0.0
 */

defined( 'MC_ABSPATH' ) || exit;

/**
 * All registered hooks (actions and filters share the same storage).
 *
 * Structure: $mc_filters[ $hook_name ][ $priority ][] = array(
 *     'callback'      => callable,
 *     'accepted_args' => int,
 * )
 *
 * @global array $mc_filters
 */
global $mc_filters;
$mc_filters = array();

/**
 * Counter for how many times each action has been fired.
 *
 * @global array<string, int> $mc_actions
 */
global $mc_actions;
$mc_actions = array();

/**
 * Counter for how many times each filter has been applied.
 *
 * @global array<string, int> $mc_filters_run
 */
global $mc_filters_run;
$mc_filters_run = array();

/**
 * Call stack of hooks currently being executed.
 *
 * @global string[] $mc_current_filter
 */
global $mc_current_filter;
$mc_current_filter = array();

/*
 * -------------------------------------------------------------------------
 *  Filter API
 * -------------------------------------------------------------------------
 */

/**
 * Register a callback for a filter hook.
 *
 * @since 1.0.0
 *
 * @param string   $hook          The filter hook name.
 * @param callable $callback      The function/method to call.
 * @param int      $priority      Execution priority (lower = earlier). Default 10.
 * @param int      $accepted_args Number of arguments the callback accepts. Default 1.
 * @return true Always returns true.
 */
function mc_add_filter(
	string $hook,
	callable $callback,
	int $priority = 10,
	int $accepted_args = 1
): bool {

	global $mc_filters;

	$mc_filters[ $hook ][ $priority ][] = array(
		'callback'      => $callback,
		'accepted_args' => $accepted_args,
	);

	return true;
}

/**
 * Apply all callbacks registered for a filter hook to a value.
 *
 * @since 1.0.0
 *
 * @param string $hook  The filter hook name.
 * @param mixed  $value The value to filter.
 * @param mixed  ...$args Additional arguments passed to each callback.
 * @return mixed The filtered value.
 */
function mc_apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {

	global $mc_filters, $mc_filters_run, $mc_current_filter;

	$mc_filters_run[ $hook ] = ( $mc_filters_run[ $hook ] ?? 0 ) + 1;

	if ( empty( $mc_filters[ $hook ] ) ) {
		return $value;
	}

	$mc_current_filter[] = $hook;

	// Sort callbacks by priority.
	$hooks = $mc_filters[ $hook ];
	ksort( $hooks, SORT_NUMERIC );

	// Prepend $value to the argument list.
	array_unshift( $args, $value );

	foreach ( $hooks as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			$value   = call_user_func_array(
				$entry['callback'],
				array_slice( $args, 0, $entry['accepted_args'] )
			);
			$args[0] = $value;
		}
	}

	array_pop( $mc_current_filter );

	return $value;
}

/**
 * Remove a previously registered filter callback.
 *
 * @since 1.0.0
 *
 * @param string   $hook     The filter hook name.
 * @param callable $callback The callback to remove.
 * @param int      $priority The priority it was registered with.
 * @return bool True if removed, false if not found.
 */
function mc_remove_filter( string $hook, callable $callback, int $priority = 10 ): bool {

	global $mc_filters;

	if ( empty( $mc_filters[ $hook ][ $priority ] ) ) {
		return false;
	}

	foreach ( $mc_filters[ $hook ][ $priority ] as $index => $entry ) {
		if ( $entry['callback'] === $callback ) {
			unset( $mc_filters[ $hook ][ $priority ][ $index ] );
			$mc_filters[ $hook ][ $priority ] = array_values(
				$mc_filters[ $hook ][ $priority ]
			);
			return true;
		}
	}

	return false;
}

/**
 * Check whether a callback is registered for a given filter hook.
 *
 * @since 1.0.0
 *
 * @param string        $hook     The filter hook name.
 * @param callable|null $callback Optional. Specific callback to look for.
 * @return bool|int False if not registered; true or the priority int if found.
 */
function mc_has_filter( string $hook, ?callable $callback = null ): bool|int {

	global $mc_filters;

	if ( ! isset( $mc_filters[ $hook ] ) ) {
		return false;
	}

	if ( null === $callback ) {
		return ! empty( $mc_filters[ $hook ] );
	}

	foreach ( $mc_filters[ $hook ] as $priority => $callbacks ) {
		foreach ( $callbacks as $entry ) {
			if ( $entry['callback'] === $callback ) {
				return $priority;
			}
		}
	}

	return false;
}

/*
 * -------------------------------------------------------------------------
 *  Action API (thin wrappers around the filter API)
 * -------------------------------------------------------------------------
 */

/**
 * Register a callback for an action hook.
 *
 * @since 1.0.0
 *
 * @param string   $hook          The action hook name.
 * @param callable $callback      The function/method to call.
 * @param int      $priority      Execution priority. Default 10.
 * @param int      $accepted_args Number of arguments the callback accepts. Default 1.
 * @return true Always returns true.
 */
function mc_add_action(
	string $hook,
	callable $callback,
	int $priority = 10,
	int $accepted_args = 1
): bool {

	return mc_add_filter( $hook, $callback, $priority, $accepted_args );
}

/**
 * Execute all callbacks registered for an action hook.
 *
 * Unlike filters the return value of each callback is discarded.
 *
 * @since 1.0.0
 *
 * @param string $hook   The action hook name.
 * @param mixed  ...$args Arguments to pass to each callback.
 * @return void
 */
function mc_do_action( string $hook, mixed ...$args ): void {

	global $mc_filters, $mc_actions, $mc_current_filter;

	$mc_actions[ $hook ] = ( $mc_actions[ $hook ] ?? 0 ) + 1;

	if ( empty( $mc_filters[ $hook ] ) ) {
		return;
	}

	$mc_current_filter[] = $hook;

	$hooks = $mc_filters[ $hook ];
	ksort( $hooks, SORT_NUMERIC );

	foreach ( $hooks as $callbacks ) {
		foreach ( $callbacks as $entry ) {
			call_user_func_array(
				$entry['callback'],
				array_slice( $args, 0, $entry['accepted_args'] )
			);
		}
	}

	array_pop( $mc_current_filter );
}

/**
 * Remove a previously registered action callback.
 *
 * @since 1.0.0
 *
 * @param string   $hook     The action hook name.
 * @param callable $callback The callback to remove.
 * @param int      $priority The priority it was registered with.
 * @return bool True if removed, false if not found.
 */
function mc_remove_action( string $hook, callable $callback, int $priority = 10 ): bool {

	return mc_remove_filter( $hook, $callback, $priority );
}

/**
 * Check whether a callback is registered for a given action hook.
 *
 * @since 1.0.0
 *
 * @param string        $hook     The action hook name.
 * @param callable|null $callback Optional. Specific callback to search for.
 * @return bool|int False if not found; true or the priority int if found.
 */
function mc_has_action( string $hook, ?callable $callback = null ): bool|int {

	return mc_has_filter( $hook, $callback );
}

/*
 * -------------------------------------------------------------------------
 *  Introspection helpers
 * -------------------------------------------------------------------------
 */

/**
 * Return the number of times an action has been fired.
 *
 * @since 1.0.0
 *
 * @param string $hook The action hook name.
 * @return int The execution count. Zero if never fired.
 */
function mc_did_action( string $hook ): int {

	global $mc_actions;
	return $mc_actions[ $hook ] ?? 0;
}

/**
 * Return the number of times a filter has been applied.
 *
 * @since 1.0.0
 *
 * @param string $hook The filter hook name.
 * @return int The application count.
 */
function mc_did_filter( string $hook ): int {

	global $mc_filters_run;
	return $mc_filters_run[ $hook ] ?? 0;
}

/**
 * Check whether a specific hook is currently being executed.
 *
 * @since 1.0.0
 *
 * @param string $hook The hook name.
 * @return bool True if the hook is currently executing.
 */
function mc_doing_action( string $hook ): bool {

	global $mc_current_filter;
	return in_array( $hook, $mc_current_filter, true );
}

/**
 * Check whether any filter/action is currently being executed.
 *
 * @since 1.0.0
 *
 * @param string $hook Optional. Specific hook to check.
 * @return bool True if a hook is executing.
 */
function mc_doing_filter( string $hook = '' ): bool {

	global $mc_current_filter;

	if ( '' === $hook ) {
		return ! empty( $mc_current_filter );
	}

	return in_array( $hook, $mc_current_filter, true );
}

/**
 * Return the name of the hook currently being executed.
 *
 * @since 1.0.0
 *
 * @return string Hook name or empty string.
 */
function mc_current_filter(): string {

	global $mc_current_filter;
	return end( $mc_current_filter ) ?: '';
}
