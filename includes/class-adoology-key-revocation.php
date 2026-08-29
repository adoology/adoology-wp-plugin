<?php
/**
 * Signed backend callback for WooCommerce API-key revocation.
 *
 * @package Adoology_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

class Adoology_Key_Revocation {

    /**
     * Register signed revocation route.
     */
    public static function register() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    /**
     * Register endpoint.
     */
    public static function register_routes() {
        register_rest_route('adoology/v1', '/revoke-key', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'revoke'),
            'permission_callback' => '__return_true',
        ));
    }

    /**
     * Verify backend signature and remove exact Woo API key.
     *
     * @param WP_REST_Request $request Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function revoke($request) {
        global $wpdb;

        $raw       = (string) $request->get_body();
        $signature = strtolower((string) $request->get_header('X-Adoology-Signature'));
        $payload   = json_decode($raw, true);
        if (!is_array($payload) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return new WP_Error('adoology_revoke_invalid', __('Invalid revocation request.', 'adoology-connector'), array('status' => 401));
        }

        $connection_id = sanitize_text_field((string) ($payload['connection_id'] ?? ''));
        $key_id        = absint($payload['key_id'] ?? 0);
        $consumer_key  = strtolower((string) ($payload['consumer_key_hash'] ?? ''));
        $timestamp     = (int) ($payload['timestamp'] ?? 0);
        $local_id      = Adoology_Connection::connection_id();
        $secret        = Adoology_Crypto::get_secret('adoology_webhook_secret');
        if ($local_id === '') {
            $local_id = (string) Adoology_Options::get('adoology_pending_revoke_connection_id', '');
            $secret   = Adoology_Crypto::get_secret('adoology_pending_revoke_secret');
            $pending_created_at = (int) Adoology_Options::get('adoology_pending_revoke_created_at', 0);
            if ($pending_created_at <= 0 || abs(time() - $pending_created_at) > DAY_IN_SECONDS) {
                self::clear_pending();
                return new WP_Error('adoology_revoke_expired', __('Revocation request expired.', 'adoology-connector'), array('status' => 401));
            }
        }

        if (!Adoology_Connection::is_valid_connection_id($connection_id) || !hash_equals($local_id, $connection_id) ||
            $key_id <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $consumer_key) || abs(time() - $timestamp) > 300 ||
            is_wp_error($secret) || $secret === '' ||
            !hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) {
            return new WP_Error('adoology_revoke_invalid', __('Invalid revocation signature.', 'adoology-connector'), array('status' => 401));
        }

        $replay_key = 'adoology_revoke_' . hash('sha256', $signature);
        if (get_transient($replay_key)) {
            return new WP_REST_Response(null, 204);
        }
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'woocommerce_api_keys',
            array('key_id' => $key_id, 'consumer_key' => $consumer_key),
            array('%d', '%s')
        );
        $key_still_exists = $deleted === 0 && $wpdb->get_var($wpdb->prepare(
            "SELECT key_id FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d",
            $key_id
        )) !== null;
        if ($deleted === false || $key_still_exists) {
            return new WP_Error('adoology_revoke_failed', __('API key revocation failed.', 'adoology-connector'), array('status' => 500));
        }
        set_transient($replay_key, 1, 10 * MINUTE_IN_SECONDS);
        return new WP_REST_Response(null, 204);
    }

    /**
     * Remove short-lived disconnect signing material.
     */
    private static function clear_pending() {
        Adoology_Options::delete('adoology_pending_revoke_connection_id');
        Adoology_Options::delete('adoology_pending_revoke_secret');
        Adoology_Options::delete('adoology_pending_revoke_created_at');
    }
}
