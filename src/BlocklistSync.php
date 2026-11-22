<?php

/**
 * Signed "blocklist changed" ping from Adoology.
 */

namespace Adoology;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class BlocklistSync
{
    /**
     * Register the signed route.
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
        register_rest_route('adoology/v1', '/blocklist-sync', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Verify the signature and schedule a download. The body carries no
     * blocklist data, so nothing in it is trusted beyond "go refresh".
     *
     * @param  WP_REST_Request  $request  Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function handle($request)
    {
        $raw = (string) $request->get_body();
        $signature = strtolower((string) $request->get_header('X-Adoology-Signature'));
        $payload = json_decode($raw, true);
        $secret = Crypto::get_secret('adoology_webhook_secret');
        $local_id = Connection::connection_id();
        $connection_id = is_array($payload) ? sanitize_text_field((string) ($payload['connection_id'] ?? '')) : '';
        $timestamp = is_array($payload) ? (int) ($payload['timestamp'] ?? 0) : 0;

        if (!is_array($payload) || !preg_match('/^[a-f0-9]{64}$/', $signature) ||
            $local_id === '' || !hash_equals($local_id, $connection_id) || abs(time() - $timestamp) > 300 ||
            is_wp_error($secret) || $secret === '' ||
            !hash_equals(hash_hmac('sha256', $raw, $secret), $signature)) {
            return new WP_Error('adoology_blocklist_sync_invalid', __('Invalid blocklist sync request.', 'adoology-for-woocommerce'), ['status' => 401]);
        }

        $replay_key = 'adoology_blocklist_sync_' . hash('sha256', $signature);
        if (!get_transient($replay_key)) {
            set_transient($replay_key, 1, 10 * MINUTE_IN_SECONDS);
            Blocklist::schedule_refresh();
        }

        return new WP_REST_Response(['accepted' => true], 202);
    }
}
