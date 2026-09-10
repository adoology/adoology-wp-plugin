<?php

/**
 * Remove Adoology connection state and legacy local artifacts.
 */
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Adoology\ApiClient;
use Adoology\Connection;
use Adoology\Scheduler;

/**
 * Uninstall plugin data for current site.
 */
function adoology_uninstall_site()
{
    global $wpdb;

    $connection_id = (string) get_option('adoology_connection_id', '');
    $preserve_remote_state = $connection_id !== '' && !class_exists(ApiClient::class);
    if (class_exists(ApiClient::class) && preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/Di', $connection_id)) {
        $remote_result = ApiClient::delete_connection(
            $connection_id,
            ApiClient::new_idempotency_key(),
            true
        );
        $preserve_remote_state = is_wp_error($remote_result) && ApiClient::error_status($remote_result) !== 404;
    }

    if (class_exists(Connection::class)) {
        Connection::delete_managed_woocommerce_credentials();
    } else {
        $webhook_table = $wpdb->prefix . 'wc_webhooks';
        $key_table = $wpdb->prefix . 'woocommerce_api_keys';
        $webhooks = $wpdb->get_results($wpdb->prepare(
            "SELECT webhook_id, name FROM {$webhook_table} WHERE name LIKE %s OR name LIKE %s",
            $wpdb->esc_like('Adoology ') . '%',
            $wpdb->esc_like('Adoology: ') . '%'
        ), ARRAY_A);
        $webhook_names = [
            'Adoology customer.created', 'Adoology customer.deleted', 'Adoology customer.updated',
            'Adoology order.created', 'Adoology order.deleted', 'Adoology order.updated',
            'Adoology product.created', 'Adoology product.deleted', 'Adoology product.updated',
            'Adoology: customer.created', 'Adoology: customer.deleted', 'Adoology: customer.updated',
            'Adoology: order.created', 'Adoology: order.deleted', 'Adoology: order.updated',
            'Adoology: product.created', 'Adoology: product.deleted', 'Adoology: product.updated',
        ];
        foreach ($webhooks as $webhook) {
            if (in_array((string) $webhook['name'], $webhook_names, true)) {
                $wpdb->delete($webhook_table, ['webhook_id' => (int) $webhook['webhook_id'], 'name' => $webhook['name']], ['%d', '%s']);
            }
        }
        $keys = $wpdb->get_results($wpdb->prepare(
            "SELECT key_id, description FROM {$key_table} WHERE description = %s OR description LIKE %s OR description LIKE %s",
            'Adoology Connector',
            $wpdb->esc_like('Adoology Connector ') . '%',
            $wpdb->esc_like('Adoology - API (') . '%'
        ), ARRAY_A);
        foreach ($keys as $key) {
            $description = (string) $key['description'];
            $managed = $description === 'Adoology Connector' ||
                (bool) preg_match('/^Adoology Connector [0-9A-HJKMNP-TV-Z]{26}$/D', $description) ||
                (bool) preg_match('/^Adoology - API \(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\)$/D', $description);
            if ($managed) {
                $wpdb->delete($key_table, ['key_id' => (int) $key['key_id'], 'description' => $description], ['%d', '%s']);
            }
        }
    }

    foreach (['adoology_connection_health_check', 'adoology_webhook_retry', 'adoology_process_events', 'adoology_cleanup_events', 'adoology_incomplete_order_lifecycle', 'adoology_complete_checkout'] as $hook) {
        if (class_exists(Scheduler::class)) {
            Scheduler::unschedule_hook($hook);
        } else {
            if (function_exists('as_unschedule_all_actions')) {
                as_unschedule_all_actions($hook);
            }
            wp_clear_scheduled_hook($hook);
        }
    }

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
        'adoology_duplicate_block_minutes',
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
        'adoology_events_continuation_state',
        'adoology_lifecycle_continuation_state',
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
