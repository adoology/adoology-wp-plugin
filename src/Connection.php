<?php

/**
 * Adoology channel connection lifecycle.
 */

namespace Adoology;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Connection
{
    const HEALTH_HOOK = 'adoology_connection_health_check';

    const KEY_DESCRIPTION = 'Adoology Connector';

    const KEY_DESCRIPTION_PREFIX = 'Adoology Connector ';

    const LEGACY_KEY_DESCRIPTION_PREFIX = 'Adoology - API (';

    const WEBHOOK_NAME_PREFIX = 'Adoology ';

    const WEBHOOK_NAMES = [
        'Adoology customer.created',
        'Adoology customer.deleted',
        'Adoology customer.updated',
        'Adoology order.created',
        'Adoology order.deleted',
        'Adoology order.updated',
        'Adoology product.created',
        'Adoology product.deleted',
        'Adoology product.updated',
        'Adoology: customer.created',
        'Adoology: customer.deleted',
        'Adoology: customer.updated',
        'Adoology: order.created',
        'Adoology: order.deleted',
        'Adoology: order.updated',
        'Adoology: product.created',
        'Adoology: product.deleted',
        'Adoology: product.updated',
    ];

    /**
     * Register scheduled callbacks.
     */
    public static function register()
    {
        add_action(self::HEALTH_HOOK, [self::class, 'health_check']);
    }

    /**
     * Whether a channel connection has been created.
     *
     * @return bool
     */
    public static function is_connected()
    {
        return self::connection_id() !== '';
    }

    /**
     * Local connection ID.
     *
     * @return string
     */
    public static function connection_id()
    {
        $connection_id = (string) Options::get('adoology_connection_id', '');

        return self::is_valid_connection_id($connection_id) ? $connection_id : '';
    }

    /**
     * Validate a backend ULID before using it in a URL.
     *
     * @param  mixed  $connection_id  Connection ID.
     * @return bool
     */
    public static function is_valid_connection_id($connection_id)
    {
        return is_string($connection_id) && (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/Di', $connection_id);
    }

    /**
     * Create a backend channel and return its Woo authorization URL.
     *
     * @return string|true|WP_Error Authorization URL, true when already created, or error.
     */
    public static function connect()
    {
        if (!Crypto::is_available()) {
            return self::fail(new WP_Error('adoology_crypto_unavailable', __('OpenSSL AES-256-GCM support is required before connecting.', 'adoology-connector')));
        }

        $token = ApiClient::token();
        if (is_wp_error($token)) {
            return self::fail($token);
        }
        if (!ApiClient::is_valid_token($token)) {
            return self::fail(new WP_Error('adoology_no_token', __('Save a valid Adoology workspace API key before connecting.', 'adoology-connector')));
        }

        $local_connection_id = self::connection_id();
        $local_secret = $local_connection_id !== '' ? Crypto::get_secret('adoology_webhook_secret') : '';
        if ($local_connection_id !== '' && !is_wp_error($local_secret) && preg_match('/^[a-f0-9]{64}$/Di', $local_secret)) {
            $refreshed = self::refresh_status();
            if (is_wp_error($refreshed)) {
                return $refreshed;
            }

            $connection_state = Options::get('adoology_connection_state', []);
            if (!is_array($connection_state) || ($connection_state['status'] ?? '') !== 'connecting') {
                return true;
            }
        }

        $idempotency = Options::get('adoology_create_idempotency_key', []);
        $idempotency_key = is_array($idempotency) && isset($idempotency['key'], $idempotency['created_at']) &&
            time() - (int) $idempotency['created_at'] < 9 * MINUTE_IN_SECONDS
            ? (string) $idempotency['key']
            : '';
        if ($idempotency_key === '') {
            $idempotency_key = ApiClient::new_idempotency_key();
            Options::update('adoology_create_idempotency_key', [
                'key' => $idempotency_key,
                'created_at' => time(),
            ]);
        }

        $created = ApiClient::create_connection(self::creation_payload(), $idempotency_key);
        if (is_wp_error($created)) {
            return self::fail($created);
        }

        $connection_id = isset($created['data']['id']) ? (string) $created['data']['id'] : '';
        $redirect_url = isset($created['meta']['redirect_uri']) ? (string) $created['meta']['redirect_uri'] : '';
        $webhook_secret = isset($created['meta']['webhook_secret']) ? (string) $created['meta']['webhook_secret'] : '';
        $existing = isset($created['meta']['existing']) && $created['meta']['existing'] === true;
        if (!self::is_valid_connection_id($connection_id)) {
            return self::fail(new WP_Error('adoology_bad_connection_response', __('Adoology returned an invalid connection identifier.', 'adoology-connector')));
        }
        if ($local_connection_id !== '' && !hash_equals(strtoupper($local_connection_id), strtoupper($connection_id))) {
            return self::fail(new WP_Error('adoology_connection_mismatch', __('Adoology returned a different store connection while repairing credentials.', 'adoology-connector')));
        }

        if ($existing && $redirect_url === '') {
            if (!preg_match('/^[a-f0-9]{64}$/Di', $webhook_secret)) {
                return self::fail(new WP_Error('adoology_repair_unavailable', __('Adoology could not restore the local webhook credential. Try again after updating the Adoology service.', 'adoology-connector')));
            }
            $stored_secret = Crypto::set_secret('adoology_webhook_secret', $webhook_secret);
            if (is_wp_error($stored_secret)) {
                return self::fail($stored_secret);
            }
            Options::update('adoology_connection_id', $connection_id);
            self::store_backend_state($created);
            Options::delete('adoology_create_idempotency_key');
            Options::delete('adoology_last_error');

            return true;
        }

        if (!preg_match('/^[a-f0-9]{64}$/Di', $webhook_secret) || !self::is_safe_authorization_url($redirect_url, $connection_id, $webhook_secret)) {
            if (!$existing) {
                $deleted = ApiClient::delete_connection($connection_id, ApiClient::new_idempotency_key());
                if (is_wp_error($deleted)) {
                    Options::update('adoology_connection_id', $connection_id);
                    self::store_backend_state($created, 'authorization_error');
                } else {
                    Options::delete('adoology_create_idempotency_key');
                }
            }

            return self::fail(new WP_Error('adoology_bad_authorization_url', __('Adoology returned an invalid WooCommerce authorization URL.', 'adoology-connector')));
        }

        $stored_secret = Crypto::set_secret('adoology_webhook_secret', $webhook_secret);
        if (is_wp_error($stored_secret)) {
            ApiClient::delete_connection($connection_id, ApiClient::new_idempotency_key());

            return self::fail($stored_secret);
        }
        Options::update('adoology_connection_id', $connection_id);
        self::store_backend_state($created, 'connecting');
        Options::delete('adoology_create_idempotency_key');
        Options::delete('adoology_last_error');

        return $redirect_url;
    }

    /**
     * Refresh remote connection status.
     *
     * @return true|WP_Error
     */
    public static function verify()
    {
        return self::refresh_status();
    }

    /**
     * Push current synchronization settings to Adoology.
     *
     * @return true|WP_Error
     */
    public static function sync_settings()
    {
        $connection_id = self::connection_id();
        if ($connection_id === '') {
            return true;
        }

        $result = ApiClient::patch_connection(
            $connection_id,
            ['settings' => self::settings_payload()],
            ApiClient::new_idempotency_key()
        );
        if (is_wp_error($result)) {
            return self::fail($result);
        }

        self::store_backend_state($result);
        Options::delete('adoology_last_error');

        return true;
    }

    /**
     * Remove backend channel and local connection state.
     *
     * @return true|WP_Error
     */
    public static function disconnect()
    {
        $connection_id = self::connection_id();
        $webhook_secret = Crypto::get_secret('adoology_webhook_secret');
        if ($connection_id !== '' && !is_wp_error($webhook_secret) && $webhook_secret !== '') {
            Options::update('adoology_pending_revoke_connection_id', $connection_id);
            $pending_secret = Crypto::set_secret('adoology_pending_revoke_secret', $webhook_secret);
            if (is_wp_error($pending_secret)) {
                self::delete_managed_woocommerce_credentials();

                return self::fail($pending_secret);
            }
            Options::update('adoology_pending_revoke_created_at', time());
        }

        if ($connection_id !== '') {
            $key = (string) Options::get('adoology_disconnect_idempotency_key', '');
            if ($key === '') {
                $key = ApiClient::new_idempotency_key();
                Options::update('adoology_disconnect_idempotency_key', $key);
            }

            $result = ApiClient::delete_connection($connection_id, $key);
            if (is_wp_error($result) && ApiClient::error_status($result) !== 404) {
                self::delete_managed_woocommerce_credentials();

                return self::fail($result);
            }
        }

        self::cleanup_local(true);
        Options::update('adoology_connection_state', [
            'status' => 'disconnected',
            'checked_at' => gmdate('Y-m-d H:i:s'),
        ]);
        Options::delete('adoology_last_error');

        return true;
    }

    /**
     * Fetch current backend status without destroying recoverable state on failure.
     *
     * @return true|WP_Error
     */
    public static function refresh_status()
    {
        $connection_id = self::connection_id();
        if ($connection_id === '') {
            return self::fail(new WP_Error('adoology_not_connected', __('Connect the store before checking its status.', 'adoology-connector')));
        }

        $result = ApiClient::get_connection($connection_id);
        if (is_wp_error($result)) {
            if (ApiClient::error_status($result) === 404) {
                // Record the missing state but keep local credentials and
                // sync state: a 404 may simply mean the configured workspace
                // key points at the wrong workspace, and destroying the live
                // connection for that would be irreversible. Only explicit
                // disconnect or uninstall performs cleanup.
                Options::update('adoology_connection_state', [
                    'status' => 'missing',
                    'checked_at' => gmdate('Y-m-d H:i:s'),
                ]);
            }

            return self::fail($result);
        }

        self::store_backend_state($result);
        Options::delete('adoology_last_error');

        return true;
    }

    /**
     * Scheduled health callback.
     */
    public static function health_check()
    {
        $pending_created_at = (int) Options::get('adoology_pending_revoke_created_at', 0);
        if ($pending_created_at > 0 && abs(time() - $pending_created_at) > DAY_IN_SECONDS) {
            Options::delete('adoology_pending_revoke_connection_id');
            Options::delete('adoology_pending_revoke_secret');
            Options::delete('adoology_pending_revoke_created_at');
        }

        if (self::is_connected()) {
            $secret = Crypto::get_secret('adoology_webhook_secret');
            if (is_wp_error($secret) || !preg_match('/^[a-f0-9]{64}$/Di', $secret)) {
                self::connect();

                return;
            }
            self::refresh_status();
        }
    }

    /**
     * Remove plugin-owned local state and legacy scaffold artifacts.
     *
     * @param  bool  $remove_api_token  Also remove workspace token.
     */
    public static function cleanup_local($remove_api_token)
    {
        self::delete_managed_woocommerce_credentials();
        Scheduler::unschedule_hook('adoology_webhook_retry');

        $options = [
            'adoology_connection_id',
            'adoology_webhook_secret',
            'adoology_wc_webhook_ids',
            'adoology_wc_api_key_id',
            'adoology_wc_consumer_key',
            'adoology_connected_at',
            'adoology_create_idempotency_key',
            'adoology_verify_idempotency_state',
            'adoology_repair_idempotency_state',
            'adoology_disconnect_idempotency_key',
            'adoology_full_sync_state',
            'adoology_webhook_retry_index',
            'adoology_webhooks_paused_by_deactivation',
            'adoology_inbound_secret',
            'adoology_stock_subscription_state',
            Events::CONTINUATION_OPTION,
            IncompleteOrders::CONTINUATION_OPTION,
        ];
        if ($remove_api_token) {
            $options[] = 'adoology_api_token';
        }

        foreach ($options as $option) {
            Options::delete($option);
        }
    }

    /**
     * Revoke locally identifiable WooCommerce credentials synchronously.
     */
    public static function delete_managed_woocommerce_credentials()
    {
        self::delete_managed_webhooks();
        self::delete_managed_api_keys();
    }

    /**
     * Backend connection creation payload. Woo credentials are collected by wc-auth.
     *
     * @return array
     */
    private static function creation_payload()
    {
        $store_name = wp_specialchars_decode((string) get_bloginfo('name'), ENT_QUOTES);
        $store_name = function_exists('mb_substr') ? mb_substr($store_name, 0, 120) : substr($store_name, 0, 120);

        $payload = [
            'type' => 'woocommerce',
            'name' => $store_name,
            'base_url' => untrailingslashit(home_url('/')),
            'timezone' => function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC',
            'presentment_currency' => get_woocommerce_currency(),
            'capabilities' => [
                'weight_unit' => (string) get_option('woocommerce_weight_unit', 'kg'),
                'rest_api_url' => untrailingslashit(rest_url('wc/v3')),
                'plugin_version' => ADOOLOGY_VERSION,
            ],
            'settings' => self::settings_payload(),
        ];
        $connection_id = self::connection_id();
        if ($connection_id !== '') {
            $payload['existing_connection_id'] = $connection_id;
        }

        return $payload;
    }

    /**
     * Backend synchronization settings.
     *
     * @return array
     */
    private static function settings_payload()
    {
        return [
            'product_auto_sync' => Options::get('adoology_product_auto_sync', 'yes') === 'yes',
            'inventory_auto_sync' => Options::get('adoology_inventory_auto_sync', 'yes') === 'yes',
            'channel_product_add_sync' => Options::get('adoology_channel_product_add_sync', 'yes') === 'yes',
        ];
    }

    /**
     * Accept only this store's canonical Woo authorization endpoint.
     *
     * @param  string  $url  Authorization URL.
     * @param  string  $connection_id  Expected callback state.
     * @param  string  $webhook_secret  One-time connection secret.
     * @return bool
     */
    private static function is_safe_authorization_url($url, $connection_id, $webhook_secret)
    {
        if ($url === '' || !wp_http_validate_url($url)) {
            return false;
        }

        $actual = wp_parse_url($url);
        $expected = wp_parse_url(home_url('/wc-auth/v1/authorize'));
        if (!is_array($actual) || !is_array($expected)) {
            return false;
        }

        foreach (['scheme', 'host', 'port', 'path'] as $part) {
            $actual_value = isset($actual[$part]) ? strtolower((string) $actual[$part]) : '';
            $expected_value = isset($expected[$part]) ? strtolower((string) $expected[$part]) : '';
            if (!hash_equals($expected_value, $actual_value)) {
                return false;
            }
        }

        if (empty($actual['query'])) {
            return false;
        }

        parse_str((string) $actual['query'], $query);
        $expected_state = $connection_id . '.' . hash_hmac('sha256', $connection_id, $webhook_secret);
        if (!isset($query['scope'], $query['user_id'], $query['callback_url']) ||
            !is_string($query['scope']) || !hash_equals('read_write', $query['scope']) ||
            !is_string($query['user_id']) || !hash_equals($expected_state, $query['user_id']) ||
            !is_string($query['callback_url'])) {
            return false;
        }

        $callback = wp_parse_url($query['callback_url']);
        $api_base = wp_parse_url(ApiClient::base_url());
        if (!is_array($callback) || !is_array($api_base) || ($callback['path'] ?? '') !== '/woocommerce/callback' ||
            isset($callback['user']) || isset($callback['pass']) || isset($callback['fragment'])) {
            return false;
        }

        foreach (['scheme', 'host', 'port'] as $part) {
            $callback_value = isset($callback[$part]) ? strtolower((string) $callback[$part]) : '';
            $api_value = isset($api_base[$part]) ? strtolower((string) $api_base[$part]) : '';
            if (!hash_equals($api_value, $callback_value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Store allowlisted backend status and synchronization fields.
     *
     * @param  array  $response  API response.
     * @param  string  $fallback_status  Status when response omits one.
     */
    private static function store_backend_state($response, $fallback_status = 'unknown')
    {
        $data = isset($response['data']) && is_array($response['data']) ? $response['data'] : [];
        $attributes = isset($data['attributes']) && is_array($data['attributes']) ? $data['attributes'] : $data;
        $status = isset($attributes['status']) && is_scalar($attributes['status'])
            ? sanitize_key((string) $attributes['status'])
            : $fallback_status;
        $state = [
            'status' => $status,
            'checked_at' => gmdate('Y-m-d H:i:s'),
        ];

        foreach (['last_full_sync_at', 'last_incremental_sync_at', 'last_error_at'] as $field) {
            if (isset($attributes[$field]) && is_string($attributes[$field])) {
                $state[$field] = sanitize_text_field($attributes[$field]);
            }
        }
        foreach (['name', 'base_url', 'timezone', 'presentment_currency'] as $field) {
            if (isset($attributes[$field]) && is_string($attributes[$field])) {
                $state[$field] = sanitize_text_field($attributes[$field]);
            }
        }
        if (isset($attributes['error_count'])) {
            $state['error_count'] = max(0, (int) $attributes['error_count']);
        }

        if ($status === 'active' && !Options::get('adoology_connected_at', '')) {
            Options::update('adoology_connected_at', gmdate('Y-m-d H:i:s'));
        }
        Options::update('adoology_connection_state', $state);
    }

    /**
     * Delete recorded and locally identifiable Adoology webhooks.
     */
    private static function delete_managed_webhooks()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'wc_webhooks';
        $webhooks = $wpdb->get_results($wpdb->prepare(
            "SELECT webhook_id, name FROM {$table} WHERE name LIKE %s OR name LIKE %s",
            $wpdb->esc_like(self::WEBHOOK_NAME_PREFIX) . '%',
            $wpdb->esc_like('Adoology: ') . '%'
        ), ARRAY_A);
        $recorded_ids = array_map('intval', (array) Options::get('adoology_wc_webhook_ids', []));
        $deleted_recorded_ids = [];
        foreach ($recorded_ids as $webhook_id) {
            $webhook = function_exists('wc_get_webhook') ? wc_get_webhook($webhook_id) : null;
            if ($webhook && in_array((string) $webhook->get_name(), self::WEBHOOK_NAMES, true)) {
                $webhook->delete(true);
                $deleted_recorded_ids[] = $webhook_id;
            }
        }
        foreach ($webhooks as $row) {
            if (in_array((int) $row['webhook_id'], $deleted_recorded_ids, true)) {
                continue;
            }
            if (!in_array((string) $row['name'], self::WEBHOOK_NAMES, true)) {
                continue;
            }
            $webhook_id = (int) $row['webhook_id'];
            $webhook = function_exists('wc_get_webhook') ? wc_get_webhook((int) $webhook_id) : null;
            if ($webhook && in_array((string) $webhook->get_name(), self::WEBHOOK_NAMES, true)) {
                $webhook->delete(true);

                continue;
            }
            $wpdb->delete($table, ['webhook_id' => $webhook_id, 'name' => $row['name']], ['%d', '%s']);
        }
    }

    /**
     * Delete recorded and locally identifiable Adoology API keys.
     */
    private static function delete_managed_api_keys()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'woocommerce_api_keys';
        $key_id = (int) Options::get('adoology_wc_api_key_id', 0);
        $consumer_key = (string) Options::get('adoology_wc_consumer_key', '');
        if ($key_id > 0 && $consumer_key !== '') {
            $wpdb->delete($table, ['key_id' => $key_id, 'consumer_key' => $consumer_key], ['%d', '%s']);
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT key_id, description FROM {$table} WHERE description = %s OR description LIKE %s OR description LIKE %s",
            self::KEY_DESCRIPTION,
            $wpdb->esc_like(self::KEY_DESCRIPTION_PREFIX) . '%',
            $wpdb->esc_like(self::LEGACY_KEY_DESCRIPTION_PREFIX) . '%'
        ), ARRAY_A);
        foreach ($rows as $row) {
            $description = (string) $row['description'];
            $managed = $description === self::KEY_DESCRIPTION ||
                (bool) preg_match('/^Adoology Connector [0-9A-HJKMNP-TV-Z]{26}(?: - API \(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\))?$/D', $description) ||
                (bool) preg_match('/^Adoology - API \(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\)$/D', $description);
            if ($managed) {
                $wpdb->delete($table, ['key_id' => (int) $row['key_id'], 'description' => $description], ['%d', '%s']);
            }
        }
    }

    /**
     * Store a redacted operational error.
     *
     * @param  WP_Error  $error  Error.
     * @return WP_Error
     */
    private static function fail($error)
    {
        $message = is_wp_error($error) ? $error->get_error_message() : __('Unknown Adoology connection error.', 'adoology-connector');
        Options::update('adoology_last_error', [
            'message' => Logger::redact_string(sanitize_text_field($message)),
            'time' => gmdate('Y-m-d H:i:s'),
        ]);

        return is_wp_error($error) ? $error : new WP_Error('adoology_connection_error', $message);
    }
}
