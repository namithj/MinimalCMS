<?php

/**
 * MinimalCMS Hook Engine
 *
 * Provides the action and filter system that powers extensibility.
 * Replaces the procedural hooks.php with a fully encapsulated class.
 *
 * @package MinimalCMS
 * @since   {version}
 */

/**
 * Class MC_Hooks
 *
 * Self-contained action/filter engine. All hook state is stored
 * in private properties — no globals required.
 *
 * @since {version}
 */
class MC_Hooks
{
	/**
	 * All registered hooks (actions and filters share the same storage).
	 *
	 * Structure: $filters[ $hook ][ $priority ][] = array(
	 *     'callback'      => callable,
	 *     'accepted_args' => int,
	 * )
	 *
	 * @since {version}
	 * @var array
	 */
	private array $filters = array();

	/**
	 * Counter for how many times each action has been fired.
	 *
	 * @since {version}
	 * @var array<string, int>
	 */
	private array $action_counts = array();

	/**
	 * Counter for how many times each filter has been applied.
	 *
	 * @since {version}
	 * @var array<string, int>
	 */
	private array $filter_counts = array();

	/**
	 * Call stack of hooks currently being executed.
	 *
	 * @since {version}
	 * @var string[]
	 */
	private array $current_filter = array();

	/*
	 * -------------------------------------------------------------------------
	 *  Filter API
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Register a callback for a filter hook.
	 *
	 * @since {version}
	 *
	 * @param string   $hook          The filter hook name.
	 * @param callable $callback      The function/method to call.
	 * @param int      $priority      Execution priority (lower = earlier). Default 10.
	 * @param int      $accepted_args Number of arguments the callback accepts. Default 1.
	 * @return bool Always returns true.
	 */
	public function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
	{

		$this->filters[$hook][$priority][] = array(
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		);

		return true;
	}

	/**
	 * Apply all callbacks registered for a filter hook to a value.
	 *
	 * @since {version}
	 *
	 * @param string $hook  The filter hook name.
	 * @param mixed  $value The value to filter.
	 * @param mixed  ...$args Additional arguments passed to each callback.
	 * @return mixed The filtered value.
	 */
	public function apply_filters(string $hook, mixed $value, mixed ...$args): mixed
	{

		$this->filter_counts[$hook] = ($this->filter_counts[$hook] ?? 0) + 1;

		if (empty($this->filters[$hook])) {
			return $value;
		}

		$this->current_filter[] = $hook;

		$hooks = $this->filters[$hook];
		ksort($hooks, SORT_NUMERIC);

		array_unshift($args, $value);

		foreach ($hooks as $callbacks) {
			foreach ($callbacks as $entry) {
				$value   = call_user_func_array(
					$entry['callback'],
					array_slice($args, 0, $entry['accepted_args'])
				);
				$args[0] = $value;
			}
		}

		array_pop($this->current_filter);

		return $value;
	}

	/**
	 * Remove a previously registered filter callback.
	 *
	 * @since {version}
	 *
	 * @param string   $hook     The filter hook name.
	 * @param callable $callback The callback to remove.
	 * @param int      $priority The priority it was registered with.
	 * @return bool True if removed, false if not found.
	 */
	public function remove_filter(string $hook, callable $callback, int $priority = 10): bool
	{

		if (empty($this->filters[$hook][$priority])) {
			return false;
		}

		foreach ($this->filters[$hook][$priority] as $index => $entry) {
			if ($entry['callback'] === $callback) {
				unset($this->filters[$hook][$priority][$index]);
				$this->filters[$hook][$priority] = array_values(
					$this->filters[$hook][$priority]
				);
				return true;
			}
		}

		return false;
	}

	/**
	 * Check whether a callback is registered for a given filter hook.
	 *
	 * @since {version}
	 *
	 * @param string        $hook     The filter hook name.
	 * @param callable|null $callback Optional. Specific callback to look for.
	 * @return bool|int False if not registered; true or the priority int if found.
	 */
	public function has_filter(string $hook, ?callable $callback = null): bool|int
	{

		if (!isset($this->filters[$hook])) {
			return false;
		}

		if (null === $callback) {
			return !empty($this->filters[$hook]);
		}

		foreach ($this->filters[$hook] as $priority => $callbacks) {
			foreach ($callbacks as $entry) {
				if ($entry['callback'] === $callback) {
					return $priority;
				}
			}
		}

		return false;
	}

	/**
	 * Remove all filters registered for a hook, optionally at a specific priority.
	 *
	 * @since {version}
	 *
	 * @param string    $hook     The filter hook name.
	 * @param int|false $priority Specific priority to clear, or false for all.
	 * @return bool Always returns true.
	 */
	public function remove_all_filters(string $hook, int|false $priority = false): bool
	{

		if (false === $priority) {
			unset($this->filters[$hook]);
		} else {
			unset($this->filters[$hook][$priority]);
		}

		return true;
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Action API
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Register a callback for an action hook.
	 *
	 * @since {version}
	 *
	 * @param string   $hook          The action hook name.
	 * @param callable $callback      The function/method to call.
	 * @param int      $priority      Execution priority. Default 10.
	 * @param int      $accepted_args Number of arguments the callback accepts. Default 1.
	 * @return bool Always returns true.
	 */
	public function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
	{

		return $this->add_filter($hook, $callback, $priority, $accepted_args);
	}

	/**
	 * Execute all callbacks registered for an action hook.
	 *
	 * @since {version}
	 *
	 * @param string $hook    The action hook name.
	 * @param mixed  ...$args Arguments to pass to each callback.
	 * @return void
	 */
	public function do_action(string $hook, mixed ...$args): void
	{

		$this->action_counts[$hook] = ($this->action_counts[$hook] ?? 0) + 1;

		if (empty($this->filters[$hook])) {
			return;
		}

		$this->current_filter[] = $hook;

		$hooks = $this->filters[$hook];
		ksort($hooks, SORT_NUMERIC);

		foreach ($hooks as $callbacks) {
			foreach ($callbacks as $entry) {
				call_user_func_array(
					$entry['callback'],
					array_slice($args, 0, $entry['accepted_args'])
				);
			}
		}

		array_pop($this->current_filter);
	}

	/**
	 * Remove a previously registered action callback.
	 *
	 * @since {version}
	 *
	 * @param string   $hook     The action hook name.
	 * @param callable $callback The callback to remove.
	 * @param int      $priority The priority it was registered with.
	 * @return bool True if removed, false if not found.
	 */
	public function remove_action(string $hook, callable $callback, int $priority = 10): bool
	{

		return $this->remove_filter($hook, $callback, $priority);
	}

	/**
	 * Check whether a callback is registered for a given action hook.
	 *
	 * @since {version}
	 *
	 * @param string        $hook     The action hook name.
	 * @param callable|null $callback Optional. Specific callback to search for.
	 * @return bool|int False if not found; true or the priority int if found.
	 */
	public function has_action(string $hook, ?callable $callback = null): bool|int
	{

		return $this->has_filter($hook, $callback);
	}

	/**
	 * Remove all actions registered for a hook, optionally at a specific priority.
	 *
	 * @since {version}
	 *
	 * @param string    $hook     The action hook name.
	 * @param int|false $priority Specific priority to clear, or false for all.
	 * @return bool Always returns true.
	 */
	public function remove_all_actions(string $hook, int|false $priority = false): bool
	{

		return $this->remove_all_filters($hook, $priority);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Introspection
	 * -------------------------------------------------------------------------
	 */

	/**
	 * Return the number of times an action has been fired.
	 *
	 * @since {version}
	 *
	 * @param string $hook The action hook name.
	 * @return int The execution count.
	 */
	public function did_action(string $hook): int
	{

		return $this->action_counts[$hook] ?? 0;
	}

	/**
	 * Return the number of times a filter has been applied.
	 *
	 * @since {version}
	 *
	 * @param string $hook The filter hook name.
	 * @return int The application count.
	 */
	public function did_filter(string $hook): int
	{

		return $this->filter_counts[$hook] ?? 0;
	}

	/**
	 * Check whether a specific filter/action is currently being executed.
	 *
	 * @since {version}
	 *
	 * @param string|null $hook Optional. Specific hook to check. Null checks if any hook is executing.
	 * @return bool True if the hook (or any hook) is executing.
	 */
	public function doing_filter(?string $hook = null): bool
	{

		if (null === $hook) {
			return !empty($this->current_filter);
		}

		return in_array($hook, $this->current_filter, true);
	}

	/**
	 * Check whether a specific action is currently being executed.
	 *
	 * @since {version}
	 *
	 * @param string|null $hook Optional. Specific hook to check.
	 * @return bool True if the hook is executing.
	 */
	public function doing_action(?string $hook = null): bool
	{

		return $this->doing_filter($hook);
	}

	/**
	 * Return the name of the hook currently being executed.
	 *
	 * @since {version}
	 *
	 * @return string|false Hook name or false if none executing.
	 */
	public function current_filter(): string|false
	{

		$current = end($this->current_filter);
		return (false === $current) ? false : $current;
	}
}
