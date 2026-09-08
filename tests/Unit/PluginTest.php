<?php

/**
 * Plugin bootstrap unit tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Plugin;
use Adoology\Tests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @coversDefaultClass \Adoology\Plugin
 */
class PluginTest extends TestCase
{
    /**
     * @covers ::cron_schedules
     */
    public function test_cron_schedules_adds_five_minute_interval()
    {
        when('__')->returnArg();

        $schedules = Plugin::cron_schedules([]);

        $this->assertArrayHasKey('adoology_five_minutes', $schedules);
        $this->assertSame(300, $schedules['adoology_five_minutes']['interval']);
    }

    /**
     * @covers ::cron_schedules
     */
    public function test_cron_schedules_preserves_existing_entries()
    {
        when('__')->returnArg();

        $schedules = Plugin::cron_schedules(['hourly' => ['interval' => 3600]]);

        $this->assertArrayHasKey('hourly', $schedules);
        $this->assertArrayHasKey('adoology_five_minutes', $schedules);
    }

    /**
     * @covers ::ensure_schedules
     */
    public function test_ensure_schedules_replaces_hourly_incomplete_order_lifecycle()
    {
        $scheduled = [];
        when('wp_next_scheduled')->justReturn(123);
        when('wp_get_schedule')->justReturn('hourly');
        when('wp_schedule_event')->alias(function ($timestamp, $recurrence, $hook) use (&$scheduled) {
            $scheduled = compact('timestamp', 'recurrence', 'hook');

            return true;
        });

        Plugin::ensure_schedules();

        $this->assertGreaterThan(time(), $scheduled['timestamp']);
        $this->assertSame('adoology_five_minutes', $scheduled['recurrence']);
        $this->assertSame('adoology_incomplete_order_lifecycle', $scheduled['hook']);
    }

    /**
     * @covers ::action_links
     */
    public function test_action_links_prepends_dashboard()
    {
        when('admin_url')->returnArg();
        when('esc_html__')->returnArg();

        $links = Plugin::action_links(['<a href="deactivate">Deactivate</a>']);

        $this->assertSame('<a href="admin.php?page=adoology">Dashboard</a>', $links[0]);
        $this->assertCount(2, $links);
    }

    /**
     * @covers ::LEGACY_HOOKS
     */
    public function test_legacy_hooks_are_listed()
    {
        $this->assertContains('adoology_webhook_retry', Plugin::LEGACY_HOOKS);
        $this->assertContains('adoology_recover_outbox', Plugin::LEGACY_HOOKS);
    }
}
