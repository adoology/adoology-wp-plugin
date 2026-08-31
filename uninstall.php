<?php

/**
 * Remove Adoology connection state and legacy local artifacts.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (!is_readable(__DIR__ . '/vendor/autoload.php')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Adoology\ApiClient;

/**
 * Uninstall plugin data for current site.
 */
function adoology_uninstall_site()
{
    global $wpdb;

    $preserve_remote_state = false;
    $connection_id = (string) get_option('adoology_connection_id', '');
    if (preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/Di', $connection_id)) {
        $remote_result = ApiClient::delete_connection(
            $connection_id,
            ApiClient::new_idempotency_key(),
            true
        );
        $preserve_remote_state = is_wp_error($remote_result) && ApiClient::error_status($remote_result) !== 404;
    }

    foreach ((array) get_option('adoology_wc_webhook_ids', []) as $webhook_id) {
        $webhook_id = (int) $webhook_id;
        if ($webhook_id <= 0) {
            continue;
        }
        if (function_exists('wc_get_webhook')) {
            $webhook = wc_get_webhook($webhook_id);
            if ($webhook && strpos((string) $webhook->get_name(), 'Adoology: ') === 0) {
                $webhook->delete(true);
            }

            continue;
        }

        $table = $wpdb->prefix . 'wc_webhooks';
        $name = $wpdb->get_var($wpdb->prepare("SELECT name FROM {$table} WHERE webhook_id = %d", $webhook_id));
        if (is_string($name) && strpos($name, 'Adoology: ') === 0) {
            $wpdb->delete($table, ['webhook_id' => $webhook_id], ['%d']);
        }
    }

    $key_id = (int) get_option('adoology_wc_api_key_id', 0);
    if ($key_id > 0) {
        $wpdb->delete(
            $wpdb->prefix . 'woocommerce_api_keys',
            ['key_id' => $key_id, 'description' => 'Adoology Connector'],
            ['%d', '%s']
        );
    }

    wp_clear_scheduled_hook('adoology_connection_health_check');
    wp_clear_scheduled_hook('adoology_webhook_retry');
    wp_clear_scheduled_hook('adoology_process_events');
    wp_clear_scheduled_hook('adoology_cleanup_events');
    wp_clear_scheduled_hook('adoology_incomplete_order_lifecycle');

    $preserved = [
        'adoology_api_base_url',
        'adoology_api_token',
        'adoology_connection_id',
        'adoology_disconnect_idempotency_key',
    ];
    $options = [
        'adoology_api_base_url',
        'adoology_api_token',
        'adoology_product_auto_sync',
        'adoology_inventory_auto_sync',
        'adoology_channel_product_add_sync',
        'adoology_tracking_enabled',
        'adoology_incomplete_timeout_minutes',
        'adoology_incomplete_expire_days',
        'adoology_fraud_enabled',
        'adoology_fraud_rate_limit',
        'adoology_duplicate_window_minutes',
        'adoology_fraud_flag_threshold',
        'adoology_fraud_hold_threshold',
        'adoology_fraud_block_threshold',
        'adoology_order_form_enabled',
        'adoology_connection_id',
        'adoology_webhook_secret',
        'adoology_wc_webhook_ids',
        'adoology_wc_api_key_id',
        'adoology_wc_consumer_key',
        'adoology_inbound_secret',
        'adoology_stock_subscription_state',
        'adoology_connected_at',
        'adoology_connection_state',
        'adoology_last_error',
        'adoology_create_idempotency_key',
        'adoology_verify_idempotency_state',
        'adoology_repair_idempotency_state',
        'adoology_disconnect_idempotency_key',
        'adoology_full_sync_state',
        'adoology_webhook_retry_index',
        'adoology_webhooks_paused_by_deactivation',
        'adoology_plugin_version',
        'adoology_db_version',
        'adoology_pending_revoke_connection_id',
        'adoology_pending_revoke_secret',
        'adoology_pending_revoke_created_at',
    ];

    foreach ($options as $option) {
        if (!$preserve_remote_state || !in_array($option, $preserved, true)) {
            delete_option($option);
        }
    }

    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}adoology_events");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}adoology_incomplete_orders");
}

if (is_multisite()) {
    $site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
    foreach ($site_ids as $site_id) {
        switch_to_blog((int) $site_id);
        adoology_uninstall_site();
        restore_current_blog();
    }
} else {
    adoology_uninstall_site();
}
