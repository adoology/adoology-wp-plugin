<?php
/**
 * Plugin bootstrap: registration, activation, deactivation, and scheduling.
 *
 * @package Adoology
 */

namespace Adoology;

final class Plugin {

	/**
	 * Legacy scaffold hooks that must never run again.
	 *
	 * @var string[]
	 */
	const LEGACY_HOOKS = array(
		'adoology_recover_outbox',
		'adoology_cleanup_outbox',
		'adoology_retry_webhook_delivery',
		'adoology_webhook_retry',
	);

	/**
	 * Register all plugin hooks.
	 */
	public static function boot() {
		add_action('plugins_loaded', array(__CLASS__, 'init'));
		add_action('before_woocommerce_init', array(__CLASS__, 'declare_woocommerce_compatibility'));
		add_filter('cron_schedules', array(__CLASS__, 'cron_schedules'));
	}

	/**
	 * Initialize the plugin once all plugins are loaded.
	 */
	public static function init() {
		if (!class_exists('WooCommerce') || !defined('WC_VERSION')) {
			add_action('admin_notices', array(__CLASS__, 'woocommerce_missing_notice'));
			return;
		}

		if (version_compare(WC_VERSION, '8.0', '<')) {
			add_action('admin_notices', array(__CLASS__, 'woocommerce_version_notice'));
			return;
		}

		Database::maybe_upgrade();
		Connection::register();
		KeyRevocation::register();
		Events::register();
		IncompleteOrders::register();
		Fraud::register();
		OrderForm::register();
		Settings::get_instance();
		self::ensure_schedules();
	}

	/**
	 * Install defaults, tables, and schedules on activation.
	 *
	 * @param bool $network_wide Network-wide activation flag.
	 */
	public static function activate($network_wide = false) {
		if (is_multisite() && $network_wide) {
			deactivate_plugins(plugin_basename(ADOOLOGY_PLUGIN_FILE), true, true);
			wp_die(esc_html__('Adoology for WooCommerce must be activated separately on each site.', 'adoology-connector'));
		}
		Options::install_defaults();
		Database::install();
		self::ensure_schedules();
		foreach (self::LEGACY_HOOKS as $legacy_hook) {
			Scheduler::unschedule_hook($legacy_hook);
		}
	}

	/**
	 * Stop plugin-owned scheduled work on deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook(Connection::HEALTH_HOOK);
		wp_clear_scheduled_hook(Events::PROCESS_HOOK);
		wp_clear_scheduled_hook(Events::CLEANUP_HOOK);
		wp_clear_scheduled_hook(IncompleteOrders::LIFECYCLE_HOOK);
		foreach (self::LEGACY_HOOKS as $legacy_hook) {
			Scheduler::unschedule_hook($legacy_hook);
		}
	}

	/**
	 * Repair required recurring jobs after activation or upgrade.
	 */
	public static function ensure_schedules() {
		if (!wp_next_scheduled(Connection::HEALTH_HOOK)) {
			wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', Connection::HEALTH_HOOK);
		}
		if (!wp_next_scheduled(Events::PROCESS_HOOK)) {
			wp_schedule_event(time() + MINUTE_IN_SECONDS, 'adoology_five_minutes', Events::PROCESS_HOOK);
		}
		if (!wp_next_scheduled(Events::CLEANUP_HOOK)) {
			wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', Events::CLEANUP_HOOK);
		}
		if (!wp_next_scheduled(IncompleteOrders::LIFECYCLE_HOOK)) {
			wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'hourly', IncompleteOrders::LIFECYCLE_HOOK);
		}
	}

	/**
	 * Add event queue recurrence.
	 *
	 * @param array $schedules Cron schedules.
	 * @return array
	 */
	public static function cron_schedules($schedules) {
		$schedules['adoology_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __('Every five minutes', 'adoology-connector'),
		);
		return $schedules;
	}

	/**
	 * Declare support for WooCommerce features.
	 */
	public static function declare_woocommerce_compatibility() {
		if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', ADOOLOGY_PLUGIN_FILE, true);
		}
	}

	/**
	 * Notice when WooCommerce is not active.
	 */
	public static function woocommerce_missing_notice() {
		if (!current_user_can('activate_plugins')) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		esc_html_e('Adoology for WooCommerce requires WooCommerce to be installed and active.', 'adoology-connector');
		echo '</p></div>';
	}

	/**
	 * Notice when WooCommerce is too old.
	 */
	public static function woocommerce_version_notice() {
		if (!current_user_can('activate_plugins')) {
			return;
		}
		echo '<div class="notice notice-error"><p>';
		esc_html_e('Adoology for WooCommerce requires WooCommerce 8.0 or newer.', 'adoology-connector');
		echo '</p></div>';
	}

	/**
	 * Settings link on the plugins list.
	 *
	 * @param array $links Action links.
	 * @return array
	 */
	public static function action_links($links) {
		$settings_link = '<a href="' . admin_url('admin.php?page=adoology') . '">' . esc_html__('Dashboard', 'adoology-connector') . '</a>';
		array_unshift($links, $settings_link);
		return $links;
	}
}
