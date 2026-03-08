<?php
/**
 * Tests for MC_Http class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Http;
use MC_Hooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Http
 */
class MCHttpClassTest extends TestCase {

	private MC_Http $http;
	private MC_Hooks $hooks;

	protected function setUp(): void {

		$this->hooks = new MC_Hooks();
		$this->http  = new MC_Http($this->hooks, 'test-secret-key-for-phpunit');
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Nonces
	 * -------------------------------------------------------------------------
	 */

	public function test_create_nonce_returns_hex_string(): void {

		$nonce = $this->http->create_nonce('test_action');
		$this->assertMatchesRegularExpression('/^[a-f0-9]{20}$/', $nonce);
	}

	public function test_create_nonce_is_deterministic(): void {

		$n1 = $this->http->create_nonce('same_action');
		$n2 = $this->http->create_nonce('same_action');
		$this->assertSame($n1, $n2);
	}

	public function test_create_nonce_differs_by_action(): void {

		$n1 = $this->http->create_nonce('action_a');
		$n2 = $this->http->create_nonce('action_b');
		$this->assertNotSame($n1, $n2);
	}

	public function test_verify_nonce_valid(): void {

		$nonce = $this->http->create_nonce('verify_test');
		$this->assertTrue($this->http->verify_nonce($nonce, 'verify_test'));
	}

	public function test_verify_nonce_invalid(): void {

		$this->assertFalse($this->http->verify_nonce('bad_nonce_value_here', 'verify_test'));
	}

	public function test_verify_nonce_empty(): void {

		$this->assertFalse($this->http->verify_nonce('', 'any'));
	}

	public function test_nonce_varies_by_user(): void {

		$this->http->set_current_user_id('user1');
		$n1 = $this->http->create_nonce('action');

		$this->http->set_current_user_id('user2');
		$n2 = $this->http->create_nonce('action');

		$this->assertNotSame($n1, $n2);
	}

	public function test_nonce_field_outputs_hidden_input(): void {

		ob_start();
		$this->http->nonce_field('test_action', '_mc_nonce');
		$output = ob_get_clean();

		$this->assertStringContainsString('type="hidden"', $output);
		$this->assertStringContainsString('name="_mc_nonce"', $output);
		$this->assertStringContainsString('value="', $output);
	}

	public function test_nonce_url_appends_parameter(): void {

		$url = $this->http->nonce_url('https://example.com/page', 'test_action');
		$this->assertStringContainsString('_mc_nonce=', $url);
		$this->assertStringContainsString('?', $url);
	}

	public function test_nonce_url_uses_ampersand_for_existing_query(): void {

		$url = $this->http->nonce_url('https://example.com/page?foo=bar', 'test_action');
		$this->assertStringContainsString('&_mc_nonce=', $url);
	}

	public function test_nonce_tick(): void {

		$tick = $this->http->nonce_tick();
		$this->assertIsInt($tick);
		$this->assertGreaterThan(0, $tick);
	}

	public function test_nonce_tick_filter(): void {

		$this->hooks->add_filter('mc_nonce_tick_length', function () {
			return 3600; // 1 hour.
		});

		$tick = $this->http->nonce_tick();
		$expected = (int) ceil(time() / (3600 / 2));
		$this->assertSame($expected, $tick);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Request introspection
	 * -------------------------------------------------------------------------
	 */

	public function test_request_method_defaults_to_get(): void {

		$original = $_SERVER['REQUEST_METHOD'] ?? null;
		unset($_SERVER['REQUEST_METHOD']);

		$this->assertSame('GET', $this->http->request_method());

		if (null !== $original) {
			$_SERVER['REQUEST_METHOD'] = $original;
		}
	}

	public function test_is_post_request(): void {

		$original = $_SERVER['REQUEST_METHOD'] ?? null;
		$_SERVER['REQUEST_METHOD'] = 'POST';

		$this->assertTrue($this->http->is_post_request());

		if (null !== $original) {
			$_SERVER['REQUEST_METHOD'] = $original;
		} else {
			unset($_SERVER['REQUEST_METHOD']);
		}
	}

	public function test_input_returns_null_for_missing_key(): void {

		$this->assertNull($this->http->input('nonexistent_key_12345'));
	}

	public function test_input_with_sanitize_callback(): void {

		$_REQUEST['test_input_key'] = '  hello  ';

		$result = $this->http->input('test_input_key', 'REQUEST', 'trim');
		$this->assertSame('hello', $result);

		unset($_REQUEST['test_input_key']);
	}

	/*
	 * -------------------------------------------------------------------------
	 *  Headers (tested where possible without exit)
	 * -------------------------------------------------------------------------
	 */

	public function test_send_404(): void {

		$this->http->send_404();
		$this->assertSame(404, http_response_code());

		// Reset.
		http_response_code(200);
	}
}
