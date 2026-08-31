<?php
/**
 * Plugin bootstrap unit tests.
 *
 * @package Adoology
 */

namespace Adoology\Tests\Unit;

use Adoology\Plugin;
use Brain\Monkey\Functions;

/**
 * @coversDefaultClass \Adoology\Plugin
 */
class PluginTest extends \Adoology\Tests\TestCase {

	/**
	 * @covers ::cron_schedules
	 */
	public function test_cron_schedules_adds_five_minute_interval() {
		Functions\when('__')->returnArg();

		$schedules = Plugin::cron_schedules(array());

		$this->assertArrayHasKey('adoology_five_minutes', $schedules);
		$this->assertSame(300, $schedules['adoology_five_minutes']['interval']);
	}

	/**
	 * @covers ::cron_schedules
	 */
	public function test_cron_schedules_preserves_existing_entries() {
		Functions\when('__')->returnArg();

		$schedules = Plugin::cron_schedules(array('hourly' => array('interval' => 3600)));

		$this->assertArrayHasKey('hourly', $schedules);
		$this->assertArrayHasKey('adoology_five_minutes', $schedules);
	}

	/**
	 * @covers ::action_links
	 */
	public function test_action_links_prepends_dashboard() {
		Functions\when('admin_url')->returnArg();
		Functions\when('esc_html__')->returnArg();

		$links = Plugin::action_links(array('<a href="deactivate">Deactivate</a>'));

		$this->assertSame('<a href="admin.php?page=adoology">Dashboard</a>', $links[0]);
		$this->assertCount(2, $links);
	}

	/**
	 * @covers ::LEGACY_HOOKS
	 */
	public function test_legacy_hooks_are_listed() {
		$this->assertContains('adoology_webhook_retry', Plugin::LEGACY_HOOKS);
		$this->assertContains('adoology_recover_outbox', Plugin::LEGACY_HOOKS);
	}
}
