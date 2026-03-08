<?php
/**
 * MC_Setup — First-run setup logic.
 *
 * Extracted from mc-admin/setup.php. Handles initial installation:
 * config seeding, key generation, and first user creation.
 *
 * @package MinimalCMS
 * @since   {version}
 */

defined('MC_ABSPATH') || exit;

/**
 * First-run setup.
 *
 * @since {version}
 */
class MC_Setup {

	/**
	 * @since {version}
	 * @var MC_Config
	 */
	private MC_Config $config;

	/**
	 * @since {version}
	 * @var MC_User_Manager
	 */
	private MC_User_Manager $users;

	/**
	 * @since {version}
	 * @var MC_Hooks
	 */
	private MC_Hooks $hooks;

	/**
	 * Constructor.
	 *
	 * @since {version}
	 *
	 * @param MC_Config       $config Configuration.
	 * @param MC_User_Manager $users  User manager.
	 * @param MC_Hooks        $hooks  Hooks engine.
	 */
	public function __construct(MC_Config $config, MC_User_Manager $users, MC_Hooks $hooks) {

		$this->config = $config;
		$this->users  = $users;
		$this->hooks  = $hooks;
	}

	/**
	 * Check if setup is needed (no users exist).
	 *
	 * @since {version}
	 *
	 * @return bool
	 */
	public function needs_setup(): bool {

		$all_users = $this->users->get_users();
		return empty($all_users);
	}

	/**
	 * Generate cryptographic keys for a new installation.
	 *
	 * @since {version}
	 *
	 * @return array{secret_key: string, encryption_key: string}
	 */
	public function generate_keys(): array {

		return array(
			'secret_key'     => bin2hex(random_bytes(32)),
			'encryption_key' => base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)),
		);
	}

	/**
	 * Seed config.json from the sample file with overrides.
	 *
	 * @since {version}
	 *
	 * @param array $overrides Key-value overrides to apply.
	 * @return true|MC_Error
	 */
	public function seed_config(array $overrides = array()): true|MC_Error {

		$sample = $this->config->all();
		if (empty($sample)) {
			$this->config->load();
			$sample = $this->config->all();
		}

		/**
		 * Filter config data before saving during setup.
		 *
		 * @since {version}
		 *
		 * @param array $config Merged config data.
		 */
		$merged = $this->hooks->apply_filters(
			'mc_setup_config',
			array_merge($sample, $overrides)
		);

		foreach ($merged as $key => $value) {
			$this->config->set($key, $value);
		}

		if (!$this->config->save()) {
			return new MC_Error('config_write_failed', 'Failed to write config.json.');
		}

		return true;
	}

	/**
	 * Run the complete setup process.
	 *
	 * Expects $data to contain at minimum:
	 *   - site_name:  string
	 *   - username:   string
	 *   - password:   string
	 *   - email:      string
	 *
	 * @since {version}
	 *
	 * @param array $data Setup data.
	 * @return true|MC_Error
	 */
	public function run(array $data): true|MC_Error {

		// Generate keys.
		$keys = $this->generate_keys();

		// Seed config.
		$config_overrides = array_merge($keys, array(
			'site_name' => $data['site_name'] ?? 'My Site',
		));

		$result = $this->seed_config($config_overrides);
		if (is_a($result, 'MC_Error')) {
			return $result;
		}

		// Create the first admin user.
		$user_result = $this->users->create_user(array(
			'username' => $data['username'] ?? '',
			'password' => $data['password'] ?? '',
			'email'    => $data['email'] ?? '',
			'role'     => 'administrator',
		));

		if (is_a($user_result, 'MC_Error')) {
			return $user_result;
		}

		/**
		 * Fires after setup finishes successfully.
		 *
		 * @since {version}
		 *
		 * @param array $data Setup data.
		 */
		$this->hooks->do_action('mc_setup_complete', $data);

		return true;
	}
}
