<?php

/**
 * Signed inbound endpoints receiving pushes from Adoology.
 *
 * Payload contract matches Adoology's outbound webhook dispatcher:
 *   POST /wp-json/adoology/v1/stock
 *   Headers: x-adoology-signature: sha256=<hex HMAC of raw body, inbound secret>
 *            x-adoology-event:     catalog.stock.updated
 *   Body: { "event": "...", "workspace_id": "...", "occurred_at": "...",
 *           "data": { "sku": "AB-123", "quantity": 12 } }
 *
 * The product may also be addressed by WooCommerce "product_id" or
 * "variation_id" inside data; SKU has the lowest precedence.
 */

namespace Adoology;

use WC_Product;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class RestController
{
    /**
     * Events this endpoint accepts.
     *
     * @var string[]
     */
    const ACCEPTED_EVENTS = [
        'catalog.stock.updated',
    ];

    /**
     * Hook route registration.
     */
    public static function register()
    {
        add_action('rest_api_init', function () {
            register_rest_route('adoology/v1', '/stock', [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'handle_stock'],
                'permission_callback' => [self::class, 'verify_signature'],
            ]);
        });
    }

    /**
     * Reject unsigned or wrongly-signed deliveries before the handler runs.
     *
     * @param  WP_REST_Request  $request  Request.
     * @return true|WP_Error
     */
    public static function verify_signature(WP_REST_Request $request)
    {
        $secret = (string) get_option('adoology_inbound_secret', '');
        if ($secret === '') {
            return new WP_Error('adoology_not_configured', __('No inbound signing secret configured.', 'adoology-connector'), ['status' => 503]);
        }

        $signature = (string) $request->get_header('x-adoology-signature');
        $expected = 'sha256=' . hash_hmac('sha256', $request->get_body(), $secret);

        if (!hash_equals($expected, $signature)) {
            return new WP_Error('adoology_bad_signature', __('Invalid webhook signature.', 'adoology-connector'), ['status' => 401]);
        }

        return true;
    }

    /**
     * Apply a stock push.
     *
     * @param  WP_REST_Request  $request  Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function handle_stock(WP_REST_Request $request)
    {
        $event = (string) $request->get_param('event');
        if (!in_array($event, self::ACCEPTED_EVENTS, true)) {
            return new WP_Error('adoology_unknown_event', sprintf(
                /* translators: %s: webhook event name */
                __('Unsupported event: %s', 'adoology-connector'),
                $event
            ), ['status' => 422]);
        }

        $data = $request->get_param('data');
        if (!is_array($data)) {
            return new WP_Error('adoology_bad_payload', __('Missing data payload.', 'adoology-connector'), ['status' => 422]);
        }

        $quantity = isset($data['quantity']) && is_numeric($data['quantity'])
            ? max(0, (int) $data['quantity'])
            : null;

        if ($quantity === null) {
            return new WP_Error('adoology_bad_quantity', __('data.quantity must be numeric.', 'adoology-connector'), ['status' => 422]);
        }

        $product = self::resolve_product($data);
        if (!$product instanceof WC_Product) {
            return new WP_Error('adoology_product_not_found', __('No product matches the payload (sku / product_id / variation_id).', 'adoology-connector'), ['status' => 404]);
        }

        wc_update_product_stock($product, $quantity, 'set');

        $product->update_meta_data('_adoology_stock_sync', [
            'quantity' => $quantity,
            'event' => $event,
            'occurred_at' => (string) $request->get_param('occurred_at'),
        ]);
        $product->save_meta_data();

        return rest_ensure_response([
            'product_id' => $product->get_id(),
            'sku' => $product->get_sku(),
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
        ]);
    }

    /**
     * Resolve the WC product: explicit variation ID > product ID > SKU.
     *
     * @param  array<string, mixed>  $data  Push payload data.
     * @return WC_Product|null
     */
    private static function resolve_product(array $data)
    {
        foreach (['variation_id', 'product_id'] as $key) {
            if (!empty($data[$key]) && is_numeric($data[$key])) {
                $product = wc_get_product((int) $data[$key]);
                if ($product instanceof WC_Product) {
                    return $product;
                }
            }
        }

        if (!empty($data['sku']) && is_string($data['sku'])) {
            $product_id = wc_get_product_id_by_sku($data['sku']);
            if ($product_id) {
                $product = wc_get_product($product_id);
                if ($product instanceof WC_Product) {
                    return $product;
                }
            }
        }

        return null;
    }
}
