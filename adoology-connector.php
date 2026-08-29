<?php
/**
 * Plugin Name: Adoology for WooCommerce
 * Plugin URI: https://adoology.com
 * Description: Connect WooCommerce to Adoology for catalog, customer, order, and inventory synchronization.
 * Version: 0.1.0
 * Author: Adoology
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 *
 * @package Adoology_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('ADOOLOGY_VERSION')) {
    define('ADOOLOGY_VERSION', '0.1.0');
}

define('ADOOLOGY_PLUGIN_FILE', __FILE__);
define('ADOOLOGY_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-options.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-crypto.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-logger.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-scheduler.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-api-client.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-database.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-connection.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-key-revocation.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-events.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-incomplete-orders.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-fraud.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-order-form.php';
require_once ADOOLOGY_PLUGIN_DIR . 'includes/class-adoology-settings.php';

/**
 * Notice when WooCommerce is not active.
 */
function adoology_woocommerce_missing_notice() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    echo '<div class="notice notice-error"><p>';
    esc_html_e('Adoology for WooCommerce requires WooCommerce to be installed and active.', 'adoology-connector');
    echo '</p></div>';
}

/**
 * Initialize the plugin.
 */
function adoology_connector_init() {
    if (!class_exists('WooCommerce') || !defined('WC_VERSION')) {
        add_action('admin_notices', 'adoology_woocommerce_missing_notice');
        return;
    }

    if (version_compare(WC_VERSION, '8.0', '<')) {
        add_action('admin_notices', 'adoology_woocommerce_version_notice');
        return;
    }

    Adoology_Database::maybe_upgrade();
    Adoology_Connection::register();
    Adoology_Key_Revocation::register();
    Adoology_Events::register();
    Adoology_Incomplete_Orders::register();
    Adoology_Fraud::register();
    Adoology_Order_Form::register();
    Adoology_Settings::get_instance();
    adoology_connector_ensure_schedules();
}
add_action('plugins_loaded', 'adoology_connector_init');

/**
 * Notice when WooCommerce is too old.
 */
function adoology_woocommerce_version_notice() {
    if (!current_user_can('activate_plugins')) {
        return;
    }
    echo '<div class="notice notice-error"><p>';
    esc_html_e('Adoology for WooCommerce requires WooCommerce 8.0 or newer.', 'adoology-connector');
    echo '</p></div>';
}

/**
 * Declare support for WooCommerce features.
 */
function adoology_declare_woocommerce_compatibility() {
    if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil')) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}
add_action('before_woocommerce_init', 'adoology_declare_woocommerce_compatibility');

/**
 * Install defaults and periodic connection health checks.
 */
function adoology_connector_activate($network_wide = false) {
    if (is_multisite() && $network_wide) {
        deactivate_plugins(plugin_basename(__FILE__), true, true);
        wp_die(esc_html__('Adoology for WooCommerce must be activated separately on each site.', 'adoology-connector'));
    }
    Adoology_Options::install_defaults();
    Adoology_Database::install();
    adoology_connector_ensure_schedules();
    foreach (array('adoology_recover_outbox', 'adoology_cleanup_outbox', 'adoology_retry_webhook_delivery', 'adoology_webhook_retry') as $legacy_hook) {
        Adoology_Scheduler::unschedule_hook($legacy_hook);
    }
}
register_activation_hook(__FILE__, 'adoology_connector_activate');

/**
 * Repair required recurring jobs after activation or upgrade.
 */
function adoology_connector_ensure_schedules() {
    if (!wp_next_scheduled(Adoology_Connection::HEALTH_HOOK)) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', Adoology_Connection::HEALTH_HOOK);
    }
    if (!wp_next_scheduled(Adoology_Events::PROCESS_HOOK)) {
        wp_schedule_event(time() + MINUTE_IN_SECONDS, 'adoology_five_minutes', Adoology_Events::PROCESS_HOOK);
    }
    if (!wp_next_scheduled(Adoology_Events::CLEANUP_HOOK)) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', Adoology_Events::CLEANUP_HOOK);
    }
    if (!wp_next_scheduled(Adoology_Incomplete_Orders::LIFECYCLE_HOOK)) {
        wp_schedule_event(time() + 5 * MINUTE_IN_SECONDS, 'hourly', Adoology_Incomplete_Orders::LIFECYCLE_HOOK);
    }
}

/**
 * Add event queue recurrence.
 *
 * @param array $schedules Cron schedules.
 * @return array
 */
function adoology_connector_cron_schedules($schedules) {
    $schedules['adoology_five_minutes'] = array(
        'interval' => 5 * MINUTE_IN_SECONDS,
        'display'  => __('Every five minutes', 'adoology-connector'),
    );
    return $schedules;
}
add_filter('cron_schedules', 'adoology_connector_cron_schedules');

/**
 * Stop plugin-owned scheduled work.
 */
function adoology_connector_deactivate() {
    wp_clear_scheduled_hook(Adoology_Connection::HEALTH_HOOK);
    wp_clear_scheduled_hook(Adoology_Events::PROCESS_HOOK);
    wp_clear_scheduled_hook(Adoology_Events::CLEANUP_HOOK);
    wp_clear_scheduled_hook(Adoology_Incomplete_Orders::LIFECYCLE_HOOK);
    foreach (array('adoology_recover_outbox', 'adoology_cleanup_outbox', 'adoology_retry_webhook_delivery', 'adoology_webhook_retry') as $legacy_hook) {
        Adoology_Scheduler::unschedule_hook($legacy_hook);
    }
}
register_deactivation_hook(__FILE__, 'adoology_connector_deactivate');

/**
 * Settings link on the plugins list.
 */
function adoology_connector_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=adoology') . '">' . esc_html__('Dashboard', 'adoology-connector') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'adoology_connector_action_links');
