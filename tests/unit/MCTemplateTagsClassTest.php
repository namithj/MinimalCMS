<?php

/**
 * Tests for MC_Template_Tags class.
 *
 * @package MinimalCMS\Tests\Unit
 */

namespace MinimalCMS\Tests\Unit;

use MC_Asset_Manager;
use MC_Cache;
use MC_Capabilities;
use MC_Config;
use MC_Content_Manager;
use MC_Content_Type_Registry;
use MC_Formatter;
use MC_Hooks;
use MC_Markdown;
use MC_Router;
use MC_Session;
use MC_Shortcodes;
use MC_Template_Loader;
use MC_Template_Tags;
use MC_Theme_Manager;
use MC_User_Manager;
use PHPUnit\Framework\TestCase;

/**
 * @covers MC_Template_Tags
 */
class MCTemplateTagsClassTest extends TestCase
{
	private MC_Template_Tags $tags;
	private MC_Hooks $hooks;
	private MC_Router $router;
	private MC_Markdown $markdown;
	private MC_Shortcodes $shortcodes;
	private MC_Content_Manager $content;
	private string $temp_dir;

	protected function setUp(): void
	{

		$this->temp_dir = sys_get_temp_dir() . '/mc_tags_test_' . uniqid() . '/';
		$content_dir    = $this->temp_dir . 'content/';
		$themes_dir     = $this->temp_dir . 'themes/';

		mkdir($content_dir, 0755, true);
		mkdir($themes_dir . 'default/', 0755, true);
		file_put_contents($themes_dir . 'default/theme.json', json_encode(array('name' => 'Default')));

		$config_path = $this->temp_dir . 'config.json';
		file_put_contents($config_path, json_encode(array('active_theme' => 'default')));

		$this->hooks      = new MC_Hooks();
		$formatter        = new MC_Formatter($this->hooks);
		$cache            = new MC_Cache($content_dir . 'cache/');
		$config           = new MC_Config($config_path, $config_path);
		$config->load();
		$this->markdown   = new MC_Markdown($this->hooks);
		$this->shortcodes = new MC_Shortcodes();

		$types = new MC_Content_Type_Registry($this->hooks, $content_dir);
		$types->register('page', array('label' => 'Page'));

		$this->content = new MC_Content_Manager($types, $this->hooks, $cache, $formatter, $content_dir);
		$this->router  = new MC_Router($this->hooks, $this->content, $types);
		$themes        = new MC_Theme_Manager($this->hooks, $config, $themes_dir);
		$assets        = new MC_Asset_Manager($this->hooks, $formatter);

		$caps    = new MC_Capabilities($this->hooks);
		$caps->initialise_roles();
		$session = new MC_Session($this->hooks, $this->temp_dir . 'sessions');
		$users   = new MC_User_Manager(
			$this->hooks,
			$formatter,
			$caps,
			$session,
			$this->temp_dir . 'users.php',
			base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES))
		);

		$loader = new MC_Template_Loader($this->hooks, $this->router, $themes);

		$this->tags = new MC_Template_Tags(
			$this->hooks,
			$this->router,
			$this->markdown,
			$this->shortcodes,
			$formatter,
			$themes,
			$assets,
			$users,
			$loader
		);
	}

	protected function tearDown(): void
	{

		$this->rm_recursive($this->temp_dir);
	}

	private function rm_recursive(string $dir): void
	{

		if (!is_dir($dir)) {
			return;
		}
		foreach (scandir($dir) as $item) {
			if ('.' === $item || '..' === $item) {
				continue;
			}
			$path = $dir . '/' . $item;
			is_dir($path) ? $this->rm_recursive($path) : unlink($path);
		}
		rmdir($dir);
	}

	public function test_instantiation(): void
	{

		$this->assertInstanceOf(MC_Template_Tags::class, $this->tags);
	}

	public function test_head_fires_hook(): void
	{

		$fired = false;
		$this->hooks->add_action('mc_head', function () use (&$fired) {
			$fired = true;
		});

		$this->tags->head();
		$this->assertTrue($fired);
	}

	public function test_body_open_fires_hook(): void
	{

		$fired = false;
		$this->hooks->add_action('mc_body_open', function () use (&$fired) {
			$fired = true;
		});

		$this->tags->body_open();
		$this->assertTrue($fired);
	}

	public function test_footer_fires_hook(): void
	{

		$fired = false;
		$this->hooks->add_action('mc_footer', function () use (&$fired) {
			$fired = true;
		});

		$this->tags->footer();
		$this->assertTrue($fired);
	}

	public function test_get_the_title_empty_when_no_content(): void
	{

		$this->assertSame('', $this->tags->get_the_title());
	}

	public function test_get_the_content_empty_when_no_content(): void
	{

		$this->assertSame('', $this->tags->get_the_content());
	}

	public function test_get_the_title_with_saved_content(): void
	{

		$this->content->save('page', 'test-page', array(
			'title'  => 'Hello World',
			'status' => 'publish',
		), '# Hello');

		// Override request path via filter so parse_request resolves this page.
		$this->hooks->add_filter('mc_request_path', function () {
			return 'test-page';
		});
		$this->router->parse_request();

		$title = $this->tags->get_the_title();
		$this->assertSame('Hello World', $title);
	}

	public function test_get_the_content_with_markdown(): void
	{

		$this->content->save('page', 'test-md', array(
			'title'  => 'MD Test',
			'status' => 'publish',
		), 'Hello **bold**');

		// Override request path via filter.
		$this->hooks->add_filter('mc_request_path', function () {
			return 'test-md';
		});
		$this->router->parse_request();

		$html = $this->tags->get_the_content();
		$this->assertStringContainsString('<strong>bold</strong>', $html);
	}

	public function test_body_class_outputs_attribute(): void
	{

		ob_start();
		$this->tags->body_class();
		$output = ob_get_clean();

		$this->assertStringStartsWith('class="', $output);
		$this->assertStringEndsWith('"', $output);
	}

	public function test_body_class_includes_extra(): void
	{

		ob_start();
		$this->tags->body_class('custom-class');
		$output = ob_get_clean();

		$this->assertStringContainsString('custom-class', $output);
	}
}
