<?php

/**
 * Signed backend callback for WooCommerce API-key revocation.
 */

namespace Adoology;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class KeyRevocation
{
    /**
     * Register signed revocation route.
     */
    public static function register()
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    /**
     * Register endpoint.
     */
    public static function register_routes()
    {
        register_rest_route('adoology/v1', '/revoke-key', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'revoke'],
            'permission_callback' => [self::class, 'verify_request'],
        ]);
    }

    /**
     * Allow only requests signed by Adoology with this store's connection secret.
     *
     * @param  WP_REST_Request  $request  Request.
     * @return true|WP_Error
     */
    public static function verify_request($request)
    {
        $verified = self::verified_fields($request);

        return is_wp_error($verified) ? $verified : true;
    }

    /**
     * Remove the exact Woo API key named by a verified request.
     *
     * @param  WP_REST_Request  $request  Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function revoke($request)
    {
        global $wpdb;

        $verified = self::verified_fields($request);
        if (is_wp_error($verified)) {
            return $verified;
        }
        $key_id = $verified['key_id'];
        $consumer_key = $verified['consumer_key'];

        $replay_key = 'adoology_revoke_' . hash('sha256', $verified['signature']);
        if (get_transient($replay_key)) {
            return new WP_REST_Response(null, 204);
        }
        $deleted = $wpdb->delete(
            $wpdb->prefix . 'woocommerce_api_keys',
            ['key_id' => $key_id, 'consumer_key' => $consumer_key],
            ['%d', '%s']
        );
        $key_still_exists = $deleted === 0 && $wpdb->get_var($wpdb->prepare(
            "SELECT key_id FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d",
            $key_id
        )) !== null;
        if ($deleted === false || $key_still_exists) {
            return new WP_Error('adoology_revoke_failed', __('API key revocation failed.', 'adoology-for-woocommerce'), ['status' => 500]);
        }
        set_transient($replay_key, 1, 10 * MINUTE_IN_SECONDS);

        return new WP_REST_Response(null, 204);
    }

    /**
     * Validate the signed revocation payload.
     *
     * @param  WP_REST_Request  $request  Request.
     * @return array{signature: string, key_id: int, consumer_key: string}|WP_Error
     */
    private static function verified_fields($request)
    {
        $raw = (string) $request->get_body();
        $signature = strtolower((string) $request->get_header('X-Adoology-Signature'));
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
            return new WP_Error('adoology_revoke_invalid', __('Invalid revocation request.', 'adoology-for-woocommerce'), ['status' => 401]);
        }

        $connection_id = sanitize_text_field((string) ($payload['connection_id'] ?? ''));
        $key_id = absint($payload['key_id'] ?? 0);
        $consumer_key = strtolower((string) ($payload['consumer_key_hash'] ?? ''));
        $timestamp = (int) ($payload['timestamp'] ?? 0);
        $local_id = Connection::connection_id();
        $secret = Crypto::get_secret('adoology_webhook_secret');
        if ($local_id === '') {
            $local_id = (string) Options::get('adoology_pending_revoke_connection_id', '');
            $secret = Crypto::get_secret('adoology_pending_revoke_secret');
            $pending_created_at = (int) Options::get('adoology_pending_revoke_created_at', 0);
            if ($pending_created_at <= 0 || abs(time() - $pending_created_at) > DAY_IN_SECONDS) {
                self::clear_pending();

                return new WP_Error('adoology_revoke_expired', __('Revocation request expired.', 'adoology-for-woocommerce'), ['status' => 401]);
            }
        }

        if (!Connection::is_valid_connection_id($connection_id) || !hash_equals($local_id, $connection_id) ||
            $key_id <= 0 || !preg_match('/^[a-f0-9]{64}$/D', $consumer_key) || abs(time() - $timestamp) > 300 ||
            is_wp_error($secret) || $secret === '' ||
            !hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) {
            return new WP_Error('adoology_revoke_invalid', __('Invalid revocation signature.', 'adoology-for-woocommerce'), ['status' => 401]);
        }

        return ['signature' => $signature, 'key_id' => $key_id, 'consumer_key' => $consumer_key];
    }

    /**
     * Remove short-lived disconnect signing material.
     */
    private static function clear_pending()
    {
        Options::delete('adoology_pending_revoke_connection_id');
        Options::delete('adoology_pending_revoke_secret');
        Options::delete('adoology_pending_revoke_created_at');
    }
}
