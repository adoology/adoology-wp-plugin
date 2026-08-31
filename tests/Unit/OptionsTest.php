<?php
/**
 * Options unit tests.
 *
 * @package Adoology
 */

namespace Adoology\Tests\Unit;

use Adoology\Options;
use Brain\Monkey\Functions;

/**
 * @coversDefaultClass \Adoology\Options
 */
class OptionsTest extends \Adoology\Tests\TestCase {

	/**
	 * @covers ::update
	 */
	public function test_update_uses_add_option_when_missing() {
		$options = array();
		Functions\when('get_option')->alias(function ($name, $default = false) use (&$options) {
            return array_key_exists($name, $options) ? $options[$name] : $default;
        });
		Functions\expect('add_option')->once()->with('adoology_api_token', 'v', '', false)->andReturn(true);
		Functions\expect('update_option')->never();

		$this->assertTrue(Options::update('adoology_api_token', 'v'));
	}

	/**
	 * @covers ::update
	 */
	public function test_update_uses_update_option_when_present() {
		Functions\when('get_option')->justReturn('existing');
		Functions\expect('update_option')->once()->with('adoology_api_token', 'v2', false)->andReturn(true);
		Functions\expect('add_option')->never();

		$this->assertTrue(Options::update('adoology_api_token', 'v2'));
	}

	/**
	 * @covers ::install_defaults
	 */
	public function test_install_defaults_adds_missing_and_removes_legacy() {
		$options = array('adoology_inbound_secret' => 'stale');
		Functions\when('get_option')->alias(function ($name, $default = false) use (&$options) {
            return array_key_exists($name, $options) ? $options[$name] : $default;
        });
		Functions\when('add_option')->alias(function ($name, $value) use (&$options) {
            $options[$name] = $value;
            return true;
        });
		Functions\when('delete_option')->alias(function ($name) use (&$options) {
            unset($options[$name]);
            return true;
        });

		Options::install_defaults();

		$this->assertSame('https://api.adoology.com', $options['adoology_api_base_url']);
		$this->assertSame('no', $options['adoology_tracking_enabled']);
		$this->assertSame(30, $options['adoology_incomplete_timeout_minutes']);
		$this->assertArrayNotHasKey('adoology_inbound_secret', $options);
	}

	/**
	 * @covers ::names
	 */
	public function test_names_covers_core_options() {
		$names = Options::names();

		$this->assertContains('adoology_api_token', $names);
		$this->assertContains('adoology_webhook_secret', $names);
		$this->assertContains('adoology_connection_id', $names);
	}
}
