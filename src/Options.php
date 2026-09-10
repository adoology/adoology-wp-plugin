<?php

/**
 * Plugin option helpers.
 */

namespace Adoology;

use stdClass;

if (!defined('ABSPATH')) {
    exit;
}

class Options
{
    /**
     * Return an option.
     *
     * @param  string  $name  Option name.
     * @param  mixed  $default  Default value.
     * @return mixed
     */
    public static function get($name, $default = false)
    {
        return get_option($name, $default);
    }

    /**
     * Store an option without autoloading it.
     *
     * @param  string  $name  Option name.
     * @param  mixed  $value  Option value.
     * @return bool
     */
    public static function update($name, $value)
    {
        $missing = new stdClass;

        if (get_option($name, $missing) === $missing) {
            return add_option($name, $value, '', false);
        }

        return update_option($name, $value, false);
    }

    /**
     * Delete an option.
     *
     * @param  string  $name  Option name.
     * @return bool
     */
    public static function delete($name)
    {
        return delete_option($name);
    }

    /**
     * Add defaults for one site.
     */
    public static function install_defaults()
    {
        $defaults = [
            'adoology_api_base_url' => 'https://api.adoology.com',
            'adoology_product_auto_sync' => 'yes',
            'adoology_inventory_auto_sync' => 'yes',
            'adoology_channel_product_add_sync' => 'yes',
            'adoology_tracking_enabled' => 'no',
            'adoology_incomplete_timeout_minutes' => 30,
            'adoology_incomplete_expire_days' => 7,
            'adoology_fraud_enabled' => 'yes',
            'adoology_fraud_rate_limit' => 5,
            'adoology_duplicate_window_minutes' => 60,
            'adoology_duplicate_block_minutes' => 5,
            'adoology_fraud_flag_threshold' => 30,
            'adoology_fraud_hold_threshold' => 60,
            'adoology_fraud_block_threshold' => 90,
            'adoology_order_form_enabled' => 'yes',
            'adoology_wc_webhook_ids' => [],
            'adoology_connection_state' => [],
            'adoology_webhook_retry_index' => [],
            'adoology_plugin_version' => defined('ADOOLOGY_VERSION') ? ADOOLOGY_VERSION : '1.0.0',
        ];

        foreach ($defaults as $name => $value) {
            $missing = new stdClass;
            if (get_option($name, $missing) === $missing) {
                add_option($name, $value, '', false);
            }
        }

        // Removed scaffold options must not leave plaintext signing material behind.
        delete_option('adoology_inbound_secret');
        delete_option('adoology_stock_subscription_state');
    }

    /**
     * All persistent options owned by this plugin.
     *
     * @return string[]
     */
    public static function names()
    {
        return [
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
            'adoology_inbound_secret',
            'adoology_stock_subscription_state',
        ];
    }
}
