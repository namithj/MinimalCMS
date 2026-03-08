<?php
/**
 * Tests for MC_Asset_Manager class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Asset_Manager;
use MC_Formatter;
use MC_Hooks;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Asset_Manager
 */
class MCAssetManagerClassTest extends TestCase {

	private MC_Asset_Manager $assets;
	private MC_Hooks $hooks;

	protected function setUp(): void {

		$this->hooks  = new MC_Hooks();
		$formatter    = new MC_Formatter($this->hooks);
		$this->assets = new MC_Asset_Manager($this->hooks, $formatter);
	}

	public function test_enqueue_style(): void {

		$this->assets->enqueue_style('main', '/css/main.css');

		ob_start();
		$this->assets->print_styles();
		$html = ob_get_clean();

		$this->assertStringContainsString('main.css', $html);
		$this->assertStringContainsString('<link', $html);
	}

	public function test_enqueue_script_in_head(): void {

		$this->assets->enqueue_script('app', '/js/app.js', false);

		ob_start();
		$this->assets->print_head_scripts();
		$html = ob_get_clean();

		$this->assertStringContainsString('app.js', $html);
		$this->assertStringContainsString('<script', $html);
	}

	public function test_enqueue_script_in_footer(): void {

		$this->assets->enqueue_script('footer-app', '/js/footer.js', true);

		ob_start();
		$this->assets->print_footer_scripts();
		$html = ob_get_clean();

		$this->assertStringContainsString('footer.js', $html);
	}

	public function test_dequeue_style(): void {

		$this->assets->enqueue_style('remove-me', '/css/remove.css');
		$this->assets->dequeue_style('remove-me');

		ob_start();
		$this->assets->print_styles();
		$html = ob_get_clean();

		$this->assertStringNotContainsString('remove.css', $html);
	}

	public function test_dequeue_script(): void {

		$this->assets->enqueue_script('remove-js', '/js/remove.js');
		$this->assets->dequeue_script('remove-js');

		ob_start();
		$this->assets->print_head_scripts();
		$this->assets->print_footer_scripts();
		$html = ob_get_clean();

		$this->assertStringNotContainsString('remove.js', $html);
	}

	public function test_localize_script(): void {

		$this->assets->enqueue_script('localized', '/js/localized.js', false);
		$this->assets->localize_script('localized', 'myData', array('key' => 'value'));

		ob_start();
		$this->assets->print_head_scripts();
		$html = ob_get_clean();

		$this->assertStringContainsString('myData', $html);
		$this->assertStringContainsString('value', $html);
	}

	public function test_style_with_custom_media(): void {

		$this->assets->enqueue_style('print-css', '/css/print.css', 'print');

		ob_start();
		$this->assets->print_styles();
		$html = ob_get_clean();

		$this->assertStringContainsString('media="print"', $html);
	}
}
