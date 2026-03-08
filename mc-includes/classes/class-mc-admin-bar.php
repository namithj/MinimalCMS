<?php

/**
 * MC_Admin_Bar — DI-based admin bar.
 *
 * Refactored from the procedural class-mc-admin-bar.php to receive
 * dependencies via constructor injection instead of relying on globals.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * Admin bar builder.
 *
 * @since {version}
 */
class MC_Admin_Bar
{
	/**
	 * Nodes registered for this render pass.
	 *
	 * @since {version}
	 * @var array<string, array>
	 */
	private array $nodes = array();

	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * @since {version}
	 * @var MC_User_Manager
	 */
	private MC_User_Manager $users;

	/**
	 * @since {version}
	 * @var MC_Router
	 */
	private MC_Router $router;

	/**
	 * @since {version}
	 * @var MC_Formatter
	 */
	private MC_Formatter $formatter;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Hooks        $hooks     Hooks engine.
	 * @param MC_User_Manager $users     User manager.
	 * @param MC_Router       $router    Router.
	 * @param MC_Formatter    $formatter Formatter.
	 */
	public function __construct(MC_Hooks $hooks, MC_User_Manager $users, MC_Router $router, MC_Formatter $formatter)
	{

		$this->hooks     = $hooks;
		$this->users     = $users;
		$this->router    = $router;
		$this->formatter = $formatter;
	}

	/**
	 * Add a node to the admin bar.
	 *
	 * @since {version}
	 *
	 * @param string $id   Node identifier.
	 * @param array  $args Node configuration.
	 * @return void
	 */
	public function add_node(string $id, array $args): void
	{

		$this->nodes[$id] = array_merge(
			array(
				'label'  => '',
				'href'   => '#',
				'group'  => 'actions',
				'order'  => 50,
				'active' => false,
			),
			$args
		);
	}

	/**
	 * Remove a node from the admin bar.
	 *
	 * @since {version}
	 *
	 * @param string $id Node identifier.
	 * @return void
	 */
	public function remove_node(string $id): void
	{

		unset($this->nodes[$id]);
	}

	/**
	 * Build default nodes and render the bar.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	public function render(): void
	{

		if (!$this->users->is_logged_in()) {
			return;
		}

		$this->nodes = array();

		/**
		 * Fires before default nodes are added.
		 *
		 * @since {version}
		 */
		$this->hooks->do_action('mc_admin_bar_init');

		$this->add_default_nodes();

		/**
		 * Filter the admin bar nodes before rendering.
		 *
		 * @since {version}
		 *
		 * @param array $nodes Nodes keyed by ID.
		 */
		$this->nodes = $this->hooks->apply_filters('mc_admin_bar_nodes', $this->nodes);

		$this->output();
	}

	/**
	 * Register built-in nodes.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	private function add_default_nodes(): void
	{

		$query = $this->router->get_query();
		$admin_url = defined('MC_ADMIN_URL') ? MC_ADMIN_URL : '/mc-admin/';

		$this->nodes['dashboard'] = array(
			'label' => 'Dashboard',
			'href'  => $admin_url,
			'group' => 'actions',
			'order' => 10,
		);

		$this->nodes['admin_pages'] = array(
			'label' => 'Pages',
			'href'  => $admin_url . 'pages.php',
			'group' => 'actions',
			'order' => 20,
		);

		if ($this->router->is_single()) {
			$edit_url = $admin_url . 'edit-page.php?type='
				. rawurlencode($query['type'] ?? 'page')
				. '&slug=' . rawurlencode($query['slug'] ?? '');

			$this->nodes['edit_page'] = array(
				'label' => 'Edit Page',
				'href'  => $edit_url,
				'group' => 'actions',
				'order' => 30,
			);
		}

		$this->nodes['logout'] = array(
			'label' => 'Log Out',
			'href'  => $this->users->logout_url(),
			'group' => 'actions',
			'order' => 100,
		);
	}

	/**
	 * Output the admin bar HTML.
	 *
	 * @since {version}
	 *
	 * @return void
	 */
	private function output(): void
	{

		$pages_nodes   = $this->get_sorted_nodes('pages');
		$actions_nodes = $this->get_sorted_nodes('actions');
		$site_url      = defined('MC_SITE_URL') ? MC_SITE_URL : '/';

		?>
<div class="mc-admin-bar" role="navigation" aria-label="Admin bar">
	<div class="mc-admin-bar__start">
		<a href="<?php echo $this->formatter->esc_url($site_url); ?>" class="mc-admin-bar__brand brand">Minimal<span>CMS</span></a>
		<?php if ($pages_nodes) : ?>
		<nav class="mc-admin-bar__pages" aria-label="Site pages">
			<?php foreach ($pages_nodes as $node) : ?>
			<a href="<?php echo $this->formatter->esc_url($node['href']); ?>"<?php echo !empty($node['active']) ? ' class="active"' : ''; ?>><?php echo $this->formatter->esc_html($node['label']); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php endif; ?>
	</div>
	<?php if ($actions_nodes) : ?>
	<nav class="mc-admin-bar__actions" aria-label="Admin actions">
		<?php foreach ($actions_nodes as $node) : ?>
		<a href="<?php echo $this->formatter->esc_url($node['href']); ?>"><?php echo $this->formatter->esc_html($node['label']); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php endif; ?>
</div>
		<?php
	}

	/**
	 * Return nodes belonging to a group, sorted by order.
	 *
	 * @since {version}
	 *
	 * @param string $group Group identifier.
	 * @return array Indexed, sorted node list.
	 */
	private function get_sorted_nodes(string $group): array
	{

		$filtered = array_filter(
			$this->nodes,
			static fn(array $n): bool => ($n['group'] ?? '') === $group
		);

		usort($filtered, static fn(array $a, array $b): int => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

		return array_values($filtered);
	}
}
