<?php

use Adoology\Plugin;

/**
 * Plugin Name: Adoology for WooCommerce
 * Plugin URI: https://adoology.com
 * Description: Connect WooCommerce to Adoology for catalog, customer, order, and inventory synchronization.
 * Version: 0.1.3
 * Author: Adoology
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('ADOOLOGY_VERSION')) {
    define('ADOOLOGY_VERSION', '0.1.3');
}

define('ADOOLOGY_PLUGIN_FILE', __FILE__);
define('ADOOLOGY_PLUGIN_DIR', plugin_dir_path(__FILE__));

if (!is_readable(ADOOLOGY_PLUGIN_DIR . 'vendor/autoload.php')) {
    add_action('admin_notices', function () {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>';
        esc_html_e('Adoology for WooCommerce is missing its Composer dependencies. Run "composer install" in the plugin directory.', 'adoology-connector');
        echo '</p></div>';
    });

    return;
}

require_once ADOOLOGY_PLUGIN_DIR . 'vendor/autoload.php';

Plugin::boot();

register_activation_hook(__FILE__, [Plugin::class, 'activate']);
register_deactivation_hook(__FILE__, [Plugin::class, 'deactivate']);
add_filter('plugin_action_links_' . plugin_basename(__FILE__), [Plugin::class, 'action_links']);
