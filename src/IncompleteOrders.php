<?php

/**
 * Incomplete checkout tracking for native checkout and landing-page forms.
 */

namespace Adoology;

use Throwable;
use WC_Customer;
use WC_Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if (!defined('ABSPATH')) {
    exit;
}

class IncompleteOrders
{
    private static $checkout_capture_context = [];

    const LIFECYCLE_HOOK = 'adoology_incomplete_order_lifecycle';

    const CAPTURE_LIMIT = 60;

    const GLOBAL_CAPTURE_LIMIT = 300;

    const LIFECYCLE_BATCH_SIZE = 100;

    const LIFECYCLE_MAX_BATCHES = 10;

    const CONTINUATION_OPTION = 'adoology_lifecycle_continuation_state';

    const COOKIE_ANON = 'adoology_anonymous_id';

    const COOKIE_CHECKOUT = 'adoology_checkout_id';

    const COMPLETION_HOOK = 'adoology_complete_checkout';

    /**
     * Register tracking and conversion hooks.
     */
    public static function register()
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_checkout_tracker']);
        add_action('woocommerce_checkout_order_processed', [self::class, 'checkout_completed'], 30, 3);
        add_action('woocommerce_store_api_cart_update_customer_from_request', [self::class, 'store_api_cart_updated'], 20, 2);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [self::class, 'store_api_capture_identity'], 20, 2);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'store_api_checkout_completed'], 30, 1);
        add_filter('woocommerce_payment_successful_result', [self::class, 'payment_successful'], PHP_INT_MAX, 2);
        add_filter('woocommerce_checkout_no_payment_needed_redirect', [self::class, 'no_payment_needed'], PHP_INT_MAX, 2);
        add_filter('rest_request_after_callbacks', [self::class, 'store_api_checkout_response'], PHP_INT_MAX, 3);
        add_action(self::COMPLETION_HOOK, [self::class, 'mark_complete'], 10, 4);
        add_action(self::LIFECYCLE_HOOK, [self::class, 'advance_lifecycle'], 10, 2);
        add_filter('wp_privacy_personal_data_exporters', [self::class, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [self::class, 'register_eraser']);
        add_action('admin_init', [self::class, 'privacy_policy_content']);
    }

    /**
     * Register public same-site capture endpoint.
     */
    public static function register_routes()
    {
        register_rest_route('adoology/v1', '/checkout', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'capture'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('adoology/v1', '/checkout-token', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'issue_capture_token'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Load tracker on native checkout.
     */
    public static function enqueue_checkout_tracker()
    {
        if (Options::get('adoology_tracking_enabled', 'no') !== 'yes' || !function_exists('is_checkout') || !is_checkout() || is_order_received_page()) {
            return;
        }

        $items = [];
        if (function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_cart() as $item) {
                $items[] = [
                    'product_id' => (int) $item['product_id'],
                    'variation_id' => (int) $item['variation_id'],
                    'quantity' => (int) $item['quantity'],
                ];
            }
        }
        self::enqueue_tracker('checkout', [
            'items' => $items,
            'value_minor' => function_exists('WC') && WC()->cart ? self::to_minor(WC()->cart->get_total('edit')) : 0,
            'currency' => get_woocommerce_currency(),
        ]);
    }

    /**
     * Enqueue tracker for one storefront flow.
     *
     * @param  string  $flow  checkout|order_form.
     * @param  array  $extra  Flow data.
     * @return array Signed capture context.
     */
    public static function enqueue_tracker($flow, $extra = [])
    {
        $flow = in_array($flow, ['checkout', 'order_form'], true) ? $flow : 'checkout';
        $context = [
            'flow' => $flow,
            'product_id' => $flow === 'order_form' ? max(0, (int) ($extra['items'][0]['product_id'] ?? 0)) : 0,
            'instance' => wp_generate_uuid4(),
            'issued_at' => microtime(true),
            'landing_page' => esc_url_raw(self::current_url()),
            'expires' => time() + 12 * HOUR_IN_SECONDS,
        ];
        $encoded_context = base64_encode((string) wp_json_encode($context));
        $signed_context = [
            'context' => $encoded_context,
            'signature' => hash_hmac('sha256', $encoded_context, wp_salt('nonce')),
        ];
        if ($flow === 'checkout') {
            self::$checkout_capture_context = $signed_context;
        }
        $checkout_context = self::$checkout_capture_context;

        wp_enqueue_script(
            'adoology-checkout-tracker',
            plugins_url('assets/js/checkout-tracker.js', ADOOLOGY_PLUGIN_FILE),
            [],
            ADOOLOGY_VERSION,
            true
        );
        wp_localize_script('adoology-checkout-tracker', 'adoologyCheckout', [
            'endpoint' => esc_url_raw(rest_url('adoology/v1/checkout')),
            'tokenEndpoint' => esc_url_raw(rest_url('adoology/v1/checkout-token')),
            'restNonce' => wp_create_nonce('wp_rest'),
            'flow' => $flow,
            'landingPage' => $context['landing_page'],
            'trackingEnabled' => Options::get('adoology_tracking_enabled', 'no') === 'yes',
            'captureContext' => $checkout_context['context'] ?? '',
            'captureSignature' => $checkout_context['signature'] ?? '',
            'data' => $extra,
        ]);

        return $signed_context;
    }

    /**
     * Capture a minimized checkout snapshot.
     *
     * @param  WP_REST_Request  $request  Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function capture($request)
    {
        if (Options::get('adoology_tracking_enabled', 'no') !== 'yes') {
            return new WP_Error('adoology_tracking_disabled', __('Checkout tracking is disabled.', 'adoology-connector'), ['status' => 403]);
        }
        if (!self::is_same_origin($request)) {
            return new WP_Error('adoology_capture_forbidden', __('Checkout capture request was rejected.', 'adoology-connector'), ['status' => 403]);
        }
        $params = $request->get_json_params();
        if (!is_array($params)) {
            return new WP_Error('adoology_capture_invalid', __('Invalid checkout data.', 'adoology-connector'), ['status' => 400]);
        }
        $checkout_id = isset($params['checkout_id']) ? sanitize_text_field($params['checkout_id']) : '';
        $anonymous_id = isset($params['anonymous_id']) ? sanitize_text_field($params['anonymous_id']) : '';
        $session_id = isset($params['session_id']) ? sanitize_text_field($params['session_id']) : '';
        $capture_token = isset($params['capture_token']) ? sanitize_text_field($params['capture_token']) : '';
        $encoded_context = isset($params['capture_context']) ? sanitize_text_field($params['capture_context']) : '';
        $context_signature = isset($params['capture_signature']) ? sanitize_text_field($params['capture_signature']) : '';
        $context = self::verify_capture_context($encoded_context, $context_signature);
        if (!is_array($context) || !self::is_uuid($checkout_id) || !self::is_uuid($anonymous_id) || !self::is_uuid($session_id) ||
            !self::verify_capture_token($capture_token, $anonymous_id, $checkout_id, $session_id, $encoded_context)) {
            return new WP_Error('adoology_capture_invalid', __('Invalid checkout identity.', 'adoology-connector'), ['status' => 400]);
        }
        if (!self::allow_capture_request($anonymous_id)) {
            return new WP_Error('adoology_capture_limited', __('Too many checkout updates.', 'adoology-connector'), ['status' => 429]);
        }

        $trusted = self::trusted_capture_data($context, $params);
        if (is_wp_error($trusted)) {
            return $trusted;
        }

        $result = self::store_snapshot($checkout_id, [
            'anonymous_id' => $anonymous_id,
            'session_id' => $session_id,
            'flow' => $trusted['flow'],
            'product_id' => $trusted['product_id'],
            'variation_id' => $trusted['variation_id'],
            'quantity' => $trusted['quantity'],
            'value_minor' => $trusted['value_minor'],
            'currency' => $trusted['currency'],
            'customer' => isset($params['customer']) && is_array($params['customer']) ? $params['customer'] : [],
            'landing_page' => $context['landing_page'],
            'form_stage' => $params['form_stage'] ?? 'started',
            'items' => $trusted['items'],
            'capture_instance' => $context['instance'] ?? '',
            'capture_sequence' => max(0, (int) ($params['capture_sequence'] ?? 0)),
            'capture_generation' => (float) ($context['issued_at'] ?? 0),
            'billing_contact' => ($params['billing_contact'] ?? false) === true,
        ]);
        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['stored' => $result === true], 202);
    }

    /**
     * Issue a short-lived token bound to browser-generated identities.
     *
     * @param  WP_REST_Request  $request  Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function issue_capture_token($request)
    {
        $params = $request->get_json_params();
        $params = is_array($params) ? $params : [];
        $encoded_context = isset($params['capture_context']) ? sanitize_text_field($params['capture_context']) : '';
        $context_signature = isset($params['capture_signature']) ? sanitize_text_field($params['capture_signature']) : '';
        $context = self::verify_capture_context($encoded_context, $context_signature);
        $enabled = Options::get('adoology_tracking_enabled', 'no') === 'yes';
        self::load_woocommerce_cart();
        if (!$enabled || !is_array($context) || !self::is_same_origin($request) || !self::allow_capture_request('token:' . self::client_ip())) {
            return new WP_Error('adoology_capture_forbidden', __('Checkout capture request was rejected.', 'adoology-connector'), ['status' => 403]);
        }
        $identity = $context['flow'] === 'checkout' ? self::identity(true) : self::identity_for_flow();
        $anonymous_id = $identity['anonymous_id'];
        $checkout_id = $identity['checkout_id'];
        $session_id = $identity['session_id'];
        $expires = time() + 2 * HOUR_IN_SECONDS;
        $response = new WP_REST_Response([
            'anonymous_id' => $anonymous_id,
            'checkout_id' => $checkout_id,
            'session_id' => $session_id,
            'expires' => $expires,
            'token' => self::capture_signature($anonymous_id, $checkout_id, $session_id, $encoded_context, $expires),
        ], 200);
        $response->header('Cache-Control', 'no-store, private, max-age=0');
        $response->header('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Resolve product and amount fields from server-owned WooCommerce state.
     *
     * @param  array  $context  Signed page context.
     * @param  array  $params  Submitted capture data.
     * @return array|WP_Error
     */
    private static function trusted_capture_data($context, $params)
    {
        $flow = (string) $context['flow'];
        if ($flow === 'checkout') {
            self::load_woocommerce_cart();
            $cart = function_exists('WC') && WC()->cart ? WC()->cart : null;
            if (!$cart) {
                return new WP_Error('adoology_capture_cart_missing', __('Checkout cart is unavailable.', 'adoology-connector'), ['status' => 400]);
            }
            $items = [];
            foreach ($cart->get_cart() as $item) {
                $items[] = [
                    'product_id' => (int) ($item['product_id'] ?? 0),
                    'variation_id' => (int) ($item['variation_id'] ?? 0),
                    'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                ];
            }
            $primary = $items[0] ?? [];

            return [
                'flow' => $flow,
                'product_id' => $primary['product_id'] ?? 0,
                'variation_id' => $primary['variation_id'] ?? 0,
                'quantity' => $primary['quantity'] ?? 1,
                'value_minor' => self::to_minor($cart->get_total('edit')),
                'currency' => get_woocommerce_currency(),
                'items' => $items,
            ];
        }

        $product_id = (int) $context['product_id'];
        $variation_id = max(0, (int) ($params['variation_id'] ?? 0));
        $quantity = max(1, min(99, (int) ($params['quantity'] ?? 1)));
        $product = wc_get_product($variation_id ?: $product_id);
        if (!$product || !$product->is_purchasable() || ($variation_id && (int) $product->get_parent_id() !== $product_id)) {
            return new WP_Error('adoology_capture_product_invalid', __('Selected product is unavailable.', 'adoology-connector'), ['status' => 400]);
        }

        return [
            'flow' => $flow,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => $quantity,
            'value_minor' => self::to_minor((float) $product->get_price() * $quantity),
            'currency' => get_woocommerce_currency(),
            'items' => [[
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'quantity' => $quantity,
            ]],
        ];
    }

    /**
     * Authenticate immutable page context used by public capture requests.
     *
     * @param  string  $encoded  Base64-encoded JSON context.
     * @param  string  $signature  Hex HMAC.
     * @return array|false
     */
    private static function verify_capture_context($encoded, $signature)
    {
        if ($encoded === '' || !preg_match('/^[a-f0-9]{64}$/Di', $signature) ||
            !hash_equals(hash_hmac('sha256', $encoded, wp_salt('nonce')), $signature)) {
            return false;
        }
        $json = base64_decode($encoded, true);
        $context = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($context) || !in_array(($context['flow'] ?? ''), ['checkout', 'order_form'], true) ||
            !isset($context['expires'], $context['landing_page']) || !is_numeric($context['expires']) ||
            (int) $context['expires'] < time() || (int) $context['expires'] > time() + 13 * HOUR_IN_SECONDS ||
            !is_string($context['landing_page'])) {
            return false;
        }
        $context['product_id'] = max(0, (int) ($context['product_id'] ?? 0));
        if ($context['flow'] === 'order_form' && $context['product_id'] <= 0) {
            return false;
        }

        return $context;
    }

    /**
     * Store or update one checkout snapshot.
     *
     * @param  string  $checkout_id  Checkout UUID.
     * @param  array  $data  Snapshot data.
     * @return bool|WP_Error
     */
    public static function store_snapshot($checkout_id, $data)
    {
        return self::with_checkout_lock($checkout_id, function () use ($checkout_id, $data) {
            global $wpdb;

            $wpdb->query('START TRANSACTION');
            try {
                $result = self::store_snapshot_locked($checkout_id, $data);
                $wpdb->query(is_wp_error($result) ? 'ROLLBACK' : 'COMMIT');

                return $result;
            } catch (Throwable $error) {
                $wpdb->query('ROLLBACK');
                throw $error;
            }
        });
    }

    private static function store_snapshot_locked($checkout_id, $data)
    {
        global $wpdb;

        if (!self::is_uuid($checkout_id)) {
            return new WP_Error('adoology_checkout_invalid', __('Invalid checkout identifier.', 'adoology-connector'));
        }

        $table = Database::incomplete_table();
        if (self::accepted_order_id($checkout_id)) {
            return false;
        }
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id, status, form_stage, customer_data, product_id, variation_id, quantity, value_minor, currency, flow FROM {$table} WHERE checkout_id = %s", $checkout_id), ARRAY_A);
        if ($existing && in_array($existing['status'], ['converted', 'recovered', 'expired'], true)) {
            return false;
        }
        $tracking = Options::get('adoology_tracking_enabled', 'no') === 'yes';
        $previous = $existing ? self::customer_payload((string) $existing['customer_data'], $checkout_id) : [];
        $instance = (string) ($data['capture_instance'] ?? '');
        $sequence = (int) ($data['capture_sequence'] ?? 0);
        $generation = isset($data['capture_generation']) ? (float) $data['capture_generation'] : null;
        if ($generation !== null && $generation < ($previous['_capture_generation'] ?? 0)) {
            return false;
        }
        if ($sequence > 0 && self::is_uuid($instance) && $sequence <= ($previous['_capture_sequences'][$instance] ?? 0)) {
            return false;
        }
        $customer = $tracking ? array_replace($previous, self::sanitize_customer($data['customer'] ?? [])) : [];
        if (!$existing && $tracking && (empty($customer['name']) || empty($customer['phone']))) {
            return false;
        }
        if (!$tracking && ($data['flow'] ?? '') !== 'order_form') {
            return false;
        }
        if (!$existing && ($data['flow'] ?? 'checkout') === 'checkout' && empty($data['items'])) {
            return false;
        }
        if ($tracking) {
            $customer['anonymous_id'] = substr(sanitize_text_field((string) ($data['anonymous_id'] ?? '')), 0, 128);
            $customer['items'] = self::sanitize_items($data['items'] ?? []);
            $customer['_billing_contact'] = !empty($previous['_billing_contact']) || !empty($data['billing_contact']);
            if ($generation !== null && $generation > ($previous['_capture_generation'] ?? 0)) {
                $customer['_capture_sequences'] = [];
            }
            if ($generation !== null) {
                $customer['_capture_generation'] = $generation;
            }
            if ($sequence > 0 && self::is_uuid($instance)) {
                $customer['_capture_sequences'][$instance] = $sequence;
            }
        }
        $json = $tracking ? wp_json_encode($customer) : '';
        $encrypted = $json && $customer ? Crypto::encrypt($json, 'adoology_checkout_' . $checkout_id) : '';
        if (is_wp_error($encrypted)) {
            return $encrypted;
        }

        $now = gmdate('Y-m-d H:i:s');
        $retention_days = min(90, max(1, (int) Options::get('adoology_incomplete_expire_days', 7)));
        $expires = gmdate('Y-m-d H:i:s', time() + $retention_days * DAY_IN_SECONDS);
        $row = [
            'session_id' => substr(sanitize_text_field((string) ($data['session_id'] ?? '')), 0, 128),
            'flow' => in_array(($data['flow'] ?? ''), ['checkout', 'order_form'], true) ? $data['flow'] : 'checkout',
            'product_id' => max(0, (int) ($data['product_id'] ?? 0)),
            'variation_id' => max(0, (int) ($data['variation_id'] ?? 0)),
            'quantity' => max(1, min(999, (int) ($data['quantity'] ?? 1))),
            'value_minor' => max(0, (int) ($data['value_minor'] ?? 0)),
            'currency' => substr(strtoupper(sanitize_key((string) ($data['currency'] ?? ''))), 0, 3),
            'customer_data' => $encrypted,
            'landing_page' => esc_url_raw((string) ($data['landing_page'] ?? '')),
            'form_stage' => substr(sanitize_key((string) ($data['form_stage'] ?? 'started')), 0, 40),
            'last_activity_at' => $now,
            'expires_at' => $expires,
            'updated_at' => $now,
        ];

        if ($row['flow'] === 'order_form' && $row['product_id'] > 0) {
            $priced_product = $row['variation_id'] ? wc_get_product($row['variation_id']) : wc_get_product($row['product_id']);
            if ($priced_product && (!$row['variation_id'] || (int) $priced_product->get_parent_id() === $row['product_id'])) {
                $row['value_minor'] = self::to_minor((float) $priced_product->get_price() * $row['quantity']);
            }
        }

        if ($existing) {
            $stages = ['started' => 1, 'details' => 2, 'leaving' => 3, 'submitted' => 4, 'completed' => 5];
            if (($stages[$existing['form_stage']] ?? 0) > ($stages[$row['form_stage']] ?? 0)) {
                $row['form_stage'] = $existing['form_stage'];
            }
            if ($wpdb->update($table, $row, ['id' => (int) $existing['id']]) === false) {
                return new WP_Error('adoology_checkout_store_failed', __('Could not store incomplete checkout.', 'adoology-connector'));
            }
            $changed = self::public_customer($previous) !== self::public_customer($customer) || ($previous['items'] ?? []) !== ($customer['items'] ?? []);
            foreach (['form_stage', 'product_id', 'variation_id', 'quantity', 'value_minor', 'currency', 'flow'] as $field) {
                $changed = $changed || (string) $existing[$field] !== (string) $row[$field];
            }
            if ($tracking && $changed) {
                $event = Events::enqueue(
                    'checkout.updated',
                    (string) ($data['anonymous_id'] ?? self::anonymous_id()),
                    $row['session_id'],
                    self::incomplete_event_properties(['checkout_id' => $checkout_id] + $row),
                    self::request_context()
                );
                if (is_wp_error($event)) {
                    return $event;
                }
            }

            return true;
        }

        $row['checkout_id'] = $checkout_id;
        $row['status'] = 'started';
        $row['created_at'] = $now;
        $inserted = $wpdb->insert($table, $row);
        if (!$inserted) {
            return new WP_Error('adoology_checkout_store_failed', __('Could not store incomplete checkout.', 'adoology-connector'));
        }

        if ($tracking) {
            $event = Events::enqueue('checkout.started', (string) ($data['anonymous_id'] ?? self::anonymous_id()), $row['session_id'], self::incomplete_event_properties($row), self::request_context());
            if (is_wp_error($event)) {
                return $event;
            }
        }

        return true;
    }

    /**
     * Bind classic checkout identity before payment is attempted.
     *
     * @param  int  $order_id  Order ID.
     * @param  array  $posted  Posted data.
     * @param  WC_Order  $order  Order.
     */
    public static function checkout_completed($order_id, $posted, $order)
    {
        if ($order instanceof WC_Order) {
            $order->update_meta_data('_adoology_checkout_id', self::identity()['checkout_id']);
            $order->save_meta_data();
        }
    }

    /**
     * Persist Store API identity before payment is attempted.
     *
     * @param  WC_Order  $order  Order.
     */
    public static function store_api_checkout_completed($order)
    {
        if ($order instanceof WC_Order) {
            if (!self::is_uuid((string) $order->get_meta('_adoology_checkout_id', true))) {
                $order->update_meta_data('_adoology_checkout_id', self::identity()['checkout_id']);
            }
            $order->save_meta_data();
        }
    }

    public static function payment_successful($result, $order_id)
    {
        if (is_array($result) && ($result['result'] ?? '') === 'success') {
            $order = wc_get_order($order_id);
            if ($order instanceof WC_Order) {
                self::mark_complete((string) $order->get_meta('_adoology_checkout_id', true), $order_id, $order);
            }
        }

        return $result;
    }

    public static function no_payment_needed($redirect, $order)
    {
        if ($order instanceof WC_Order) {
            self::mark_complete((string) $order->get_meta('_adoology_checkout_id', true), $order->get_id(), $order);
        }

        return $redirect;
    }

    public static function store_api_checkout_response($response, $handler, $request)
    {
        if ($request->get_method() !== 'POST' || !preg_match('#^/wc/store(?:/v1)?/checkout(?:/([0-9]+))?/?$#D', $request->get_route(), $route) || is_wp_error($response)) {
            return $response;
        }
        $rest_response = rest_ensure_response($response);
        $data = $rest_response->get_data();
        if ($rest_response->get_status() >= 200 && $rest_response->get_status() < 300 && is_array($data) && ($data['payment_result']['payment_status'] ?? '') === 'success' && !empty($data['order_id']) && (empty($route[1]) || (int) $route[1] === (int) $data['order_id'])) {
            $order = wc_get_order((int) $data['order_id']);
            if ($order instanceof WC_Order) {
                self::mark_complete((string) $order->get_meta('_adoology_checkout_id', true), $order->get_id(), $order);
            }
        }

        return $response;
    }

    /**
     * Persist checkout identity sent through WooCommerce Blocks extension data.
     *
     * @param  WC_Order  $order  Order.
     * @param  WP_REST_Request  $request  Request.
     */
    public static function store_api_capture_identity($order, $request)
    {
        if (!$order instanceof WC_Order || !is_object($request)) {
            return;
        }
        if (preg_match('#/checkout/[0-9]+/?$#D', $request->get_route()) && self::is_uuid((string) $order->get_meta('_adoology_checkout_id', true))) {
            return;
        }
        $order->update_meta_data('_adoology_checkout_id', self::identity()['checkout_id']);
    }

    /**
     * Capture billing details saved by the WooCommerce Blocks cart API.
     *
     * @param  WC_Customer  $customer  Customer updated by the Store API.
     * @param  WP_REST_Request  $request  Store API request.
     */
    public static function store_api_cart_updated($customer, $request)
    {
        global $wpdb;

        if (Options::get('adoology_tracking_enabled', 'no') !== 'yes' || !$customer instanceof WC_Customer) {
            return;
        }

        $cookie_checkout = isset($_COOKIE[self::COOKIE_CHECKOUT]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_CHECKOUT])) : '';
        if (self::accepted_order_id($cookie_checkout)) {
            return;
        }
        $identity = self::identity();
        if (self::accepted_order_id($identity['checkout_id'])) {
            return;
        }
        $cart = function_exists('WC') ? WC()->cart : null;
        $items = [];
        if ($cart) {
            foreach ($cart->get_cart() as $item) {
                $items[] = [
                    'product_id' => (int) $item['product_id'],
                    'variation_id' => (int) $item['variation_id'],
                    'quantity' => (int) $item['quantity'],
                ];
            }
        }
        $primary_item = $items[0] ?? [];

        $billing_name = trim($customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name());
        $shipping_name = trim($customer->get_shipping_first_name() . ' ' . $customer->get_shipping_last_name());
        $billing_address = trim($customer->get_billing_address_1() . ' ' . $customer->get_billing_address_2());
        $shipping_address = trim($customer->get_shipping_address_1() . ' ' . $customer->get_shipping_address_2());
        $billing = (array) $request->get_param('billing_address');
        $shipping = (array) $request->get_param('shipping_address');
        $contact = [];
        $fields = ['name' => ['first_name', 'last_name'], 'phone' => ['phone'], 'email' => ['email'], 'address' => ['address_1', 'address_2'], 'city' => ['city'], 'postcode' => ['postcode'], 'country' => ['country']];
        $stored = $wpdb->get_var($wpdb->prepare('SELECT customer_data FROM ' . Database::incomplete_table() . ' WHERE checkout_id = %s', $identity['checkout_id']));
        $previous = self::customer_payload((string) $stored, $identity['checkout_id']);
        $billing_contact = !empty($previous['_billing_contact']) || $billing_name !== '' || $customer->get_billing_phone() !== '' || $billing_address !== '';
        $default_billing = !$billing_contact;
        foreach ($fields as $field => $parts) {
            $has_billing = (bool) array_intersect($parts, array_keys($billing));
            $has_shipping = (bool) array_intersect($parts, array_keys($shipping));
            if (!$has_billing && !$has_shipping) {
                continue;
            }
            $billing_value = $field === 'name' ? $billing_name : ($field === 'address' ? $billing_address : $customer->{'get_billing_' . $field}());
            $shipping_value = $field === 'name' ? $shipping_name : ($field === 'address' ? $shipping_address : ($field === 'email' ? '' : $customer->{'get_shipping_' . $field}()));
            $contact[$field] = $has_billing && (!$default_billing || $billing_value !== '' || !$has_shipping) ? $billing_value : $shipping_value;
        }
        self::store_snapshot($identity['checkout_id'], [
            'anonymous_id' => $identity['anonymous_id'],
            'session_id' => $identity['session_id'],
            'flow' => 'checkout',
            'product_id' => $primary_item['product_id'] ?? 0,
            'variation_id' => $primary_item['variation_id'] ?? 0,
            'quantity' => $primary_item['quantity'] ?? 1,
            'value_minor' => $cart ? self::to_minor($cart->get_total('edit')) : 0,
            'currency' => get_woocommerce_currency(),
            'customer' => $contact,
            'billing_contact' => $billing_contact,
            'landing_page' => wp_get_referer() ?: wc_get_checkout_url(),
            'form_stage' => 'details',
            'items' => $items,
        ]);
    }

    /**
     * Convert or recover a tracked checkout.
     *
     * @param  string  $checkout_id  Checkout UUID.
     * @param  int  $order_id  Order ID.
     * @param  WC_Order|null  $order  Order.
     * @param  int  $attempt  Retry attempt.
     * @param  string  $acting_anonymous_id  Identity of the submitting browser.
     */
    public static function mark_complete($checkout_id, $order_id, $order = null, $attempt = 0, $acting_anonymous_id = '')
    {
        if (!self::is_uuid($checkout_id)) {
            return;
        }
        try {
            $result = self::with_checkout_lock($checkout_id, function () use ($checkout_id, $order_id, $acting_anonymous_id) {
                global $wpdb;

                $order = wc_get_order($order_id);
                if (!$order instanceof WC_Order) {
                    return true;
                }
                $table = Database::incomplete_table();
                $row = $wpdb->get_row($wpdb->prepare("SELECT id, status, session_id, customer_data FROM {$table} WHERE checkout_id = %s", $checkout_id), ARRAY_A);

                // A stored checkout only links to an order submitted with its
                // own identity; a known UUID alone must not hijack someone
                // else's checkout correlation.
                if (is_array($row)) {
                    $customer = self::customer_payload((string) $row['customer_data'], $checkout_id);
                    $stored_anonymous_id = is_string($customer['anonymous_id'] ?? null) ? (string) $customer['anonymous_id'] : '';
                    if (self::is_uuid($stored_anonymous_id) && self::is_uuid($acting_anonymous_id) && !hash_equals($stored_anonymous_id, $acting_anonymous_id)) {
                        return new WP_Error('adoology_checkout_identity_mismatch', 'Checkout belongs to a different session.');
                    }
                }

                $accepted_key = '_adoology_checkout_accepted_' . $checkout_id;
                $event_key = '_adoology_checkout_completion_' . $checkout_id;
                $order->update_meta_data('_adoology_checkout_id', $checkout_id);
                $order->update_meta_data($accepted_key, $checkout_id);
                $order->save_meta_data();
                $order->read_meta_data(true);
                if ($order->get_meta($accepted_key, true) !== $checkout_id) {
                    return new WP_Error('adoology_checkout_marker_failed', 'Could not persist checkout acceptance.');
                }

                if (!$order->get_meta($event_key, true)) {
                    $wpdb->query('START TRANSACTION');
                    try {
                        $event = 'untracked';
                        if (Options::get('adoology_tracking_enabled', 'no') === 'yes' || !empty($row['customer_data'])) {
                            $status = $row && in_array($row['status'], ['incomplete', 'submitting_recovery', 'recovered'], true) ? 'recovered' : 'converted';
                            // The completion event reports the identity stored
                            // on the row itself; proof-less recovery is not
                            // trusted here.
                            $rowCustomer = self::customer_payload((string) ($row['customer_data'] ?? ''), $checkout_id);
                            $rowAnonymous = is_string($rowCustomer['anonymous_id'] ?? null) ? (string) $rowCustomer['anonymous_id'] : '';
                            $rowSession = is_array($row) && self::is_uuid((string) $row['session_id']) ? (string) $row['session_id'] : '';
                            $identity = self::is_uuid($rowAnonymous) && $rowSession !== ''
                                ? ['anonymous_id' => $rowAnonymous, 'checkout_id' => $checkout_id, 'session_id' => $rowSession]
                                : self::identity_for_checkout($checkout_id);
                            $event = Events::enqueue('checkout.' . $status, $identity['anonymous_id'], $identity['session_id'], [
                                'checkout_id' => $checkout_id,
                                'order_id' => (int) $order_id,
                                'status' => $status,
                            ]);
                            if (is_wp_error($event)) {
                                $wpdb->query('ROLLBACK');

                                return $event;
                            }
                        }
                        $order->update_meta_data($event_key, $event);
                        $order->save_meta_data();
                        $order->read_meta_data(true);
                        if ($order->get_meta($event_key, true) !== $event) {
                            $wpdb->query('ROLLBACK');

                            return new WP_Error('adoology_checkout_marker_failed', 'Could not persist checkout completion.');
                        }
                        $wpdb->query('COMMIT');
                    } catch (Throwable $error) {
                        $wpdb->query('ROLLBACK');
                        throw $error;
                    }
                }
                if ($wpdb->delete($table, ['checkout_id' => $checkout_id]) === false) {
                    return new WP_Error('adoology_checkout_delete_failed', 'Could not remove completed checkout.');
                }

                return true;
            });
        } catch (Throwable $error) {
            $result = new WP_Error('adoology_checkout_cleanup_failed', $error->getMessage());
        }
        if (is_wp_error($result)) {
            // Cleanup must not turn an accepted payment into a failed checkout.
            Logger::log('error', 'Checkout cleanup will be retried.', ['order_id' => (int) $order_id]);
            Scheduler::schedule_single(time() + MINUTE_IN_SECONDS, self::COMPLETION_HOOK, [$checkout_id, (int) $order_id, null, (int) $attempt + 1]);
        }
    }

    public static function accepted_order_id($checkout_id)
    {
        if (!self::is_uuid($checkout_id)) {
            return 0;
        }
        $orders = wc_get_orders([
            'limit' => 1,
            'return' => 'ids',
            'meta_key' => '_adoology_checkout_accepted_' . $checkout_id,
            'meta_value' => $checkout_id,
        ]);

        return (int) ($orders[0] ?? 0);
    }

    private static function with_checkout_lock($checkout_id, $callback)
    {
        global $wpdb;

        if (!self::is_uuid($checkout_id)) {
            return new WP_Error('adoology_checkout_invalid', __('Invalid checkout identifier.', 'adoology-connector'));
        }
        // Serialize captures and completion even after the snapshot row is deleted.
        $lock = 'ado_checkout_' . substr(hash('sha256', $wpdb->prefix . $checkout_id), 0, 48);
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 5)', $lock)) !== 1) {
            return new WP_Error('adoology_checkout_busy', __('Checkout is being updated. Please try again.', 'adoology-connector'), ['status' => 409]);
        }
        try {
            return $callback();
        } finally {
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock));
        }
    }

    /**
     * Advance idle checkouts to incomplete and expired states.
     */
    public static function advance_lifecycle($kind = null, $continuation_token = '')
    {
        try {
            self::advance_lifecycle_batch($continuation_token);
        } finally {
            self::release_lifecycle_continuation($continuation_token);
        }
    }

    private static function advance_lifecycle_batch($continuation_token)
    {
        global $wpdb;

        $table = Database::incomplete_table();
        $timeout = min(1440, max(5, (int) Options::get('adoology_incomplete_timeout_minutes', 30))) * MINUTE_IN_SECONDS;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = CASE WHEN status = 'submitting_recovery' THEN 'incomplete' ELSE 'started' END, updated_at = %s WHERE status IN ('submitting','submitting_recovery') AND order_id = 0 AND updated_at < %s",
            gmdate('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS)
        ));
        $cutoff = gmdate('Y-m-d H:i:s', time() - $timeout);
        $has_more = false;
        for ($batch = 0; $batch < self::LIFECYCLE_MAX_BATCHES; $batch++) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, checkout_id, session_id, flow, product_id, variation_id, quantity, value_minor, currency, form_stage, customer_data FROM {$table} WHERE status = 'started' AND last_activity_at < %s LIMIT %d",
                $cutoff,
                self::LIFECYCLE_BATCH_SIZE
            ), ARRAY_A);
            foreach ($rows as $row) {
                $updated = $wpdb->update($table, ['status' => 'incomplete', 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => (int) $row['id'], 'status' => 'started']);
                if ($updated && Options::get('adoology_tracking_enabled', 'no') === 'yes') {
                    Events::enqueue(
                        'checkout.incomplete',
                        self::anonymous_for_checkout($row['checkout_id']),
                        $row['session_id'],
                        self::incomplete_event_properties($row)
                    );
                }
            }
            $has_more = count($rows) === self::LIFECYCLE_BATCH_SIZE;
            if (!$has_more) {
                break;
            }
        }
        $now = gmdate('Y-m-d H:i:s');
        for ($batch = 0; $batch < self::LIFECYCLE_MAX_BATCHES; $batch++) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, checkout_id, session_id, status, flow, product_id, variation_id, quantity, value_minor, currency, form_stage, customer_data FROM {$table} WHERE status IN ('started','incomplete') AND expires_at < %s LIMIT %d",
                $now,
                self::LIFECYCLE_BATCH_SIZE
            ), ARRAY_A);
            foreach ($rows as $row) {
                $updated = $wpdb->update($table, ['status' => 'expired', 'updated_at' => $now], ['id' => (int) $row['id'], 'status' => $row['status']]);
                if ($updated && Options::get('adoology_tracking_enabled', 'no') === 'yes') {
                    Events::enqueue(
                        'checkout.expired',
                        self::anonymous_for_checkout($row['checkout_id']),
                        $row['session_id'],
                        self::incomplete_event_properties($row)
                    );
                }
            }
            $has_more = count($rows) === self::LIFECYCLE_BATCH_SIZE || $has_more;
            if (count($rows) < self::LIFECYCLE_BATCH_SIZE) {
                break;
            }
        }
        $has_more = self::purge_checkout_rows('expired', $now) || $has_more;
        $retention = min(90, max(1, (int) Options::get('adoology_incomplete_expire_days', 7))) * DAY_IN_SECONDS;
        $has_more = self::purge_checkout_rows('terminal', gmdate('Y-m-d H:i:s', time() - $retention)) || $has_more;
        if ($has_more) {
            $run_at = time() + 1;
            self::schedule_lifecycle_continuation($run_at, $continuation_token);
        }
    }

    private static function schedule_lifecycle_continuation($timestamp, $current_token)
    {
        $token = self::claim_lifecycle_continuation($current_token);
        if ($token === '') {
            return;
        }
        $scheduled = Scheduler::schedule_single($timestamp, self::LIFECYCLE_HOOK, ['continuation', $token]);
        if (is_wp_error($scheduled)) {
            self::release_lifecycle_continuation($token);
        }
    }

    private static function claim_lifecycle_continuation($current_token)
    {
        $state = Options::get(self::CONTINUATION_OPTION, []);
        if ($current_token !== '') {
            if (!is_array($state) || !isset($state['token']) || !hash_equals((string) $state['token'], (string) $current_token)) {
                return '';
            }
        } else {
            if (is_array($state) && isset($state['created_at']) && time() - (int) $state['created_at'] >= 10 * MINUTE_IN_SECONDS) {
                Options::delete(self::CONTINUATION_OPTION);
            }
            $token = wp_generate_uuid4();
            if (!add_option(self::CONTINUATION_OPTION, ['token' => $token, 'created_at' => time()], '', false)) {
                return '';
            }

            return $token;
        }

        $token = wp_generate_uuid4();
        Options::update(self::CONTINUATION_OPTION, ['token' => $token, 'created_at' => time()]);

        return $token;
    }

    private static function release_lifecycle_continuation($token)
    {
        $state = Options::get(self::CONTINUATION_OPTION, []);
        if ($token !== '' && is_array($state) && isset($state['token']) && hash_equals((string) $state['token'], (string) $token)) {
            Options::delete(self::CONTINUATION_OPTION);
        }
    }

    /**
     * Atomically claim checkout before creating an order.
     *
     * @param  string  $checkout_id  Checkout UUID.
     * @return array|WP_Error
     */
    public static function claim_submission($checkout_id)
    {
        return self::with_checkout_lock($checkout_id, fn () => self::claim_submission_locked($checkout_id));
    }

    private static function claim_submission_locked($checkout_id)
    {
        global $wpdb;

        $order_id = self::accepted_order_id($checkout_id);
        if ($order_id) {
            return ['claimed' => false, 'order_id' => $order_id];
        }
        $table = Database::incomplete_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, status, order_id FROM {$table} WHERE checkout_id = %s", $checkout_id), ARRAY_A);
        if (!$row) {
            return new WP_Error('adoology_checkout_missing', __('Checkout could not be prepared.', 'adoology-connector'));
        }
        if (in_array($row['status'], ['converted', 'recovered'], true) && (int) $row['order_id'] > 0) {
            return ['claimed' => false, 'order_id' => (int) $row['order_id']];
        }
        if (!in_array($row['status'], ['started', 'incomplete'], true)) {
            return new WP_Error('adoology_checkout_processing', __('This order submission is already being processed.', 'adoology-connector'));
        }
        $next = $row['status'] === 'incomplete' ? 'submitting_recovery' : 'submitting';
        $updated = $wpdb->update($table, ['status' => $next, 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => (int) $row['id'], 'status' => $row['status']]);

        return $updated ? ['claimed' => true, 'order_id' => 0] : new WP_Error('adoology_checkout_processing', __('This order submission is already being processed.', 'adoology-connector'));
    }

    /**
     * Release failed order-form claim for safe retry.
     *
     * @param  string  $checkout_id  Checkout UUID.
     */
    public static function release_submission($checkout_id)
    {
        global $wpdb;

        $table = Database::incomplete_table();
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = CASE WHEN status = 'submitting_recovery' THEN 'incomplete' ELSE 'started' END, updated_at = %s WHERE checkout_id = %s AND status IN ('submitting','submitting_recovery') AND order_id = 0",
            gmdate('Y-m-d H:i:s'),
            $checkout_id
        ));
    }

    /**
     * Current anonymous, checkout, and session identity.
     *
     * @return array
     */
    public static function identity($renew = false)
    {
        $session = function_exists('WC') ? WC()->session : null;
        $identity = $session ? $session->get('adoology_checkout_identity') : null;
        $cookie_checkout = isset($_COOKIE[self::COOKIE_CHECKOUT]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_CHECKOUT])) : '';
        if (!is_array($identity) || !self::is_uuid($identity['anonymous_id'] ?? '') || !self::is_uuid($identity['checkout_id'] ?? '') || !self::is_uuid($identity['session_id'] ?? '')) {
            // Concurrent token/cart requests must derive the same initial tuple.
            $seed = get_current_blog_id() . '|' . ($session ? $session->get_customer_id() : self::anonymous_id());
            $identity = [
                'anonymous_id' => self::identity_uuid('anonymous|' . $seed),
                'checkout_id' => self::is_uuid($cookie_checkout) ? $cookie_checkout : self::identity_uuid('checkout|' . $seed),
                'session_id' => self::identity_uuid('session|' . $seed),
            ];
        } elseif (self::is_uuid($cookie_checkout) && self::accepted_order_id($identity['checkout_id']) && !self::accepted_order_id($cookie_checkout)) {
            $identity['checkout_id'] = $cookie_checkout;
        }
        // Only a fresh checkout-token request can start another checkout generation.
        while ($renew && self::accepted_order_id($identity['checkout_id'])) {
            $identity['checkout_id'] = self::identity_uuid('next|' . $identity['checkout_id']);
        }
        if ($session) {
            $session->set('adoology_checkout_identity', $identity);
            if (method_exists($session, 'set_customer_session_cookie')) {
                $session->set_customer_session_cookie(true);
            }
        }
        if ($renew) {
            self::set_cookie(self::COOKIE_ANON, $identity['anonymous_id'], time() + YEAR_IN_SECONDS);
            self::set_cookie(self::COOKIE_CHECKOUT, $identity['checkout_id'], time() + 7 * DAY_IN_SECONDS);
        }

        return $identity;
    }

    private static function identity_uuid($seed)
    {
        $hash = hash_hmac('sha256', $seed, wp_salt('nonce'));

        return substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-4' . substr($hash, 13, 3) . '-a' . substr($hash, 17, 3) . '-' . substr($hash, 20, 12);
    }

    /**
     * Deterministic identity for the landing order-form flow, seeded by the
     * stable anonymous cookie so a regenerated page keeps the same triple
     * instead of losing correlation.
     *
     * @return array{anonymous_id: string, checkout_id: string, session_id: string}
     */
    private static function identity_for_flow()
    {
        $seed = get_current_blog_id() . '|' . self::anonymous_id();

        return [
            'anonymous_id' => self::identity_uuid('flow-anon|' . $seed),
            'checkout_id' => self::identity_uuid('flow-checkout|' . $seed),
            'session_id' => self::identity_uuid('flow-session|' . $seed),
        ];
    }

    /**
     * Recover identity already stored for one captured checkout.
     *
     * @param  string  $checkout_id  Checkout UUID.
     * @param  array  $submitted  Signed browser identity fallback.
     * @param  int  $product_id  Expected order-form product.
     * @return array
     */
    public static function identity_for_checkout($checkout_id, $submitted = [], $product_id = 0)
    {
        global $wpdb;

        if (!self::is_uuid($checkout_id)) {
            return self::identity();
        }
        $row = $wpdb->get_row($wpdb->prepare(
            'SELECT session_id, customer_data FROM ' . Database::incomplete_table() . ' WHERE checkout_id = %s',
            $checkout_id
        ), ARRAY_A);
        $customer = is_array($row) ? self::customer_payload((string) $row['customer_data'], $checkout_id) : [];
        $anonymous_id = is_string($customer['anonymous_id'] ?? null) ? $customer['anonymous_id'] : '';
        $session_id = is_array($row) ? (string) $row['session_id'] : '';

        $submitted_anonymous_id = sanitize_text_field((string) ($submitted['anonymous_id'] ?? ''));
        $submitted_session_id = sanitize_text_field((string) ($submitted['session_id'] ?? ''));
        $token = sanitize_text_field((string) ($submitted['capture_token'] ?? ''));
        $encoded_context = sanitize_text_field((string) ($submitted['capture_context'] ?? ''));
        $signature = sanitize_text_field((string) ($submitted['capture_signature'] ?? ''));
        $context = self::verify_capture_context($encoded_context, $signature);
        $proof_valid = self::is_uuid($submitted_anonymous_id) && self::is_uuid($submitted_session_id) && is_array($context)
            && $context['flow'] === 'order_form' && (int) $context['product_id'] === (int) $product_id
            && self::verify_capture_token($token, $submitted_anonymous_id, $checkout_id, $submitted_session_id, $encoded_context);

        if (self::is_uuid($anonymous_id) && self::is_uuid($session_id)) {
            // The stored identity is only recoverable with valid signed proof
            // that carries the very same ids; knowing the checkout UUID alone
            // is not ownership.
            if ($proof_valid && hash_equals($anonymous_id, $submitted_anonymous_id) && hash_equals($session_id, $submitted_session_id)) {
                return [
                    'anonymous_id' => $anonymous_id,
                    'checkout_id' => $checkout_id,
                    'session_id' => $session_id,
                ];
            }

            return self::identity();
        }

        if ($proof_valid) {
            return [
                'anonymous_id' => $submitted_anonymous_id,
                'checkout_id' => $checkout_id,
                'session_id' => $submitted_session_id,
            ];
        }

        return self::identity();
    }

    /**
     * Current checkout UUID.
     *
     * @return string
     */
    public static function checkout_id()
    {
        $value = isset($_COOKIE[self::COOKIE_CHECKOUT]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_CHECKOUT])) : '';
        if (!self::is_uuid($value)) {
            $value = wp_generate_uuid4();
            self::set_cookie(self::COOKIE_CHECKOUT, $value, time() + 7 * DAY_IN_SECONDS);
        }

        return $value;
    }

    /**
     * Current anonymous browser ID.
     *
     * @return string
     */
    public static function anonymous_id()
    {
        $value = isset($_COOKIE[self::COOKIE_ANON]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_ANON])) : '';
        if (!self::is_uuid($value)) {
            $value = wp_generate_uuid4();
            self::set_cookie(self::COOKIE_ANON, $value, time() + YEAR_IN_SECONDS);
        }

        return $value;
    }

    /**
     * Minimized request context.
     *
     * @return array
     */
    public static function request_context()
    {
        return [
            'ip' => self::client_ip(),
            'user_agent' => substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
        ];
    }

    /**
     * Trust direct peer address only.
     *
     * @return string
     */
    public static function client_ip()
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }

    /**
     * Stable request limiter subject without trusting forwarded IP headers.
     * Logged-in customers bind to their account id; otherwise the client IP
     * is preferred so clearing cookies cannot reset the component, and
     * cookie-backed identifiers are last resorts.
     *
     * @return string
     */
    public static function client_identifier()
    {
        if (function_exists('WC') && WC()->session) {
            $session_id = (string) WC()->session->get_customer_id();
            if ($session_id !== '' && (int) $session_id > 0) {
                return 'customer:' . $session_id;
            }
        }

        $ip = self::client_ip();
        if ($ip !== '') {
            return 'ip:' . hash_hmac('sha256', $ip, wp_salt('nonce'));
        }

        if (function_exists('WC') && WC()->session) {
            $session_id = (string) WC()->session->get_customer_id();
            if ($session_id !== '') {
                return 'session:' . $session_id;
            }
        }

        $anonymous_id = isset($_COOKIE[self::COOKIE_ANON]) ? sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE_ANON])) : '';

        return self::is_uuid($anonymous_id) ? $anonymous_id : 'unknown';
    }

    /**
     * Convert decimal amount into current currency minor units.
     *
     * @param  mixed  $amount  Amount.
     * @return int
     */
    public static function to_minor($amount)
    {
        return max(0, (int) round((float) $amount * 10 ** wc_get_price_decimals()));
    }

    /**
     * Register WordPress privacy exporter.
     *
     * @param  array  $exporters  Exporters.
     * @return array
     */
    public static function register_exporter($exporters)
    {
        $exporters['adoology-incomplete-orders'] = [
            'exporter_friendly_name' => __('Adoology incomplete orders', 'adoology-connector'),
            'callback' => [self::class, 'export_personal_data'],
        ];

        return $exporters;
    }

    /**
     * Register WordPress privacy eraser.
     *
     * @param  array  $erasers  Erasers.
     * @return array
     */
    public static function register_eraser($erasers)
    {
        $erasers['adoology-incomplete-orders'] = [
            'eraser_friendly_name' => __('Adoology incomplete orders', 'adoology-connector'),
            'callback' => [self::class, 'erase_personal_data'],
        ];

        return $erasers;
    }

    /**
     * Suggest disclosure text for WordPress privacy policy guide.
     */
    public static function privacy_policy_content()
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        wp_add_privacy_policy_content(
            __('Adoology for WooCommerce', 'adoology-connector'),
            wp_kses_post(__('<p>When incomplete-order tracking is enabled, this store temporarily records checkout contact, address, product, session, IP address, and browser information. Data is encrypted locally, sent to the connected Adoology workspace for order recovery and risk analysis, and removed according to the configured retention period.</p>', 'adoology-connector'))
        );
    }

    /**
     * Export encrypted checkout data matching email.
     *
     * @param  string  $email  Email.
     * @param  int  $page  Page.
     * @return array
     */
    public static function export_personal_data($email, $page = 1)
    {
        $matches = self::privacy_rows($email, $page);
        $data = [];
        foreach ($matches['rows'] as $match) {
            $fields = [];
            foreach ($match['customer'] as $name => $value) {
                $fields[] = ['name' => ucfirst(str_replace('_', ' ', $name)), 'value' => is_array($value) ? wp_json_encode($value) : $value];
            }
            foreach (['checkout_id', 'session_id', 'flow', 'status', 'product_id', 'variation_id', 'quantity', 'value_minor', 'currency', 'landing_page', 'form_stage', 'risk_score', 'order_id', 'created_at', 'updated_at'] as $field) {
                $fields[] = ['name' => ucfirst(str_replace('_', ' ', $field)), 'value' => $match['row'][$field]];
            }
            foreach (self::events_for_anonymous($match['customer']['anonymous_id'] ?? '') as $event) {
                $fields[] = ['name' => __('Adoology event', 'adoology-connector'), 'value' => wp_json_encode($event)];
            }
            $data[] = [
                'group_id' => 'adoology-incomplete-orders',
                'group_label' => __('Adoology incomplete orders', 'adoology-connector'),
                'item_id' => 'adoology-checkout-' . $match['row']['id'],
                'data' => $fields,
            ];
        }

        return ['data' => $data, 'done' => $matches['done']];
    }

    /**
     * Erase encrypted checkout data matching email.
     *
     * @param  string  $email  Email.
     * @param  int  $page  Page.
     * @return array
     */
    public static function erase_personal_data($email, $page = 1)
    {
        global $wpdb;

        $cursor_key = 'adoology_erase_' . hash_hmac('sha256', strtolower((string) $email), wp_salt('nonce'));
        if ((int) $page <= 1) {
            delete_transient($cursor_key);
        }
        $matches = self::privacy_rows($email, 1, 100, (int) get_transient($cursor_key));
        $removed = false;
        foreach ($matches['rows'] as $match) {
            $anonymous_id = (string) ($match['customer']['anonymous_id'] ?? '');
            if ($anonymous_id !== '') {
                $wpdb->delete(Database::events_table(), ['anonymous_id' => $anonymous_id], ['%s']);
            }
            $wpdb->delete(Database::incomplete_table(), ['id' => (int) $match['row']['id']], ['%d']);
            $removed = true;
        }
        if ($matches['done']) {
            delete_transient($cursor_key);
        } else {
            set_transient($cursor_key, $matches['last_id'], DAY_IN_SECONDS);
        }

        return [
            'items_removed' => $removed,
            'items_retained' => $removed,
            'messages' => $removed ? [__('Local data was erased. Data already delivered to Adoology must be erased from the connected workspace separately.', 'adoology-connector')] : [],
            'done' => $matches['done'],
        ];
    }

    private static function anonymous_for_checkout($checkout_id)
    {
        global $wpdb;
        $payload = $wpdb->get_var($wpdb->prepare('SELECT customer_data FROM ' . Database::incomplete_table() . ' WHERE checkout_id = %s', $checkout_id));
        if ($payload) {
            $decrypted = Crypto::decrypt($payload, 'adoology_checkout_' . $checkout_id);
            $data = is_wp_error($decrypted) ? null : json_decode($decrypted, true);
            if (is_array($data) && !empty($data['anonymous_id'])) {
                return $data['anonymous_id'];
            }
        }

        return self::anonymous_id();
    }

    /**
     * Build trusted product details for an incomplete checkout event.
     *
     * @param  array  $row  Stored checkout row.
     * @return array
     */
    private static function incomplete_event_properties($row)
    {
        $product_id = (int) ($row['product_id'] ?? 0);
        $variation_id = (int) ($row['variation_id'] ?? 0);
        $customer = self::customer_payload((string) ($row['customer_data'] ?? ''), $row['checkout_id']);
        $properties = [
            'checkout_id' => $row['checkout_id'],
            'flow' => $row['flow'],
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => (int) $row['quantity'],
            'value_minor' => (int) $row['value_minor'],
            'currency' => $row['currency'],
            'form_stage' => $row['form_stage'],
            'customer' => self::public_customer($customer),
        ];
        $product_name = self::product_name($product_id, $variation_id);
        if ($product_name !== '') {
            $properties['product_name'] = $product_name;
        }
        $product_image = self::product_image($product_id, $variation_id);
        if ($product_image !== '') {
            $properties['product_image'] = $product_image;
        }
        $items = self::event_items($customer['items'] ?? []);
        if ($items !== []) {
            $properties['items'] = $items;
        }

        return $properties;
    }

    /**
     * Decrypt one stored checkout customer payload.
     *
     * @param  string  $payload  Encrypted customer data.
     * @param  string  $checkout_id  Checkout UUID.
     * @return array
     */
    private static function customer_payload($payload, $checkout_id)
    {
        if ($payload === '') {
            return [];
        }
        $decrypted = Crypto::decrypt($payload, 'adoology_checkout_' . $checkout_id);
        $data = is_wp_error($decrypted) ? null : json_decode($decrypted, true);

        return is_array($data) ? $data : [];
    }

    /**
     * Build trusted product data for every captured cart item.
     *
     * @param  array  $items  Stored cart items.
     * @return array
     */
    private static function event_items($items)
    {
        $result = [];
        foreach (self::sanitize_items($items) as $item) {
            if ($item['product_id'] <= 0) {
                continue;
            }
            $product_name = self::product_name($item['product_id'], $item['variation_id']);
            if ($product_name !== '') {
                $item['product_name'] = $product_name;
            }
            $product_image = self::product_image($item['product_id'], $item['variation_id']);
            if ($product_image !== '') {
                $item['product_image'] = $product_image;
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * Resolve a trusted WooCommerce product or variation name.
     *
     * @param  int  $product_id  Parent product ID.
     * @param  int  $variation_id  Variation ID.
     * @return string
     */
    private static function product_name($product_id, $variation_id)
    {
        $product = wc_get_product($variation_id ?: $product_id);
        if (!$product) {
            return '';
        }
        $name = sanitize_text_field((string) $product->get_name());

        return function_exists('mb_substr') ? mb_substr($name, 0, 190) : substr($name, 0, 190);
    }

    /**
     * Resolve a trusted WooCommerce product or variation thumbnail URL.
     *
     * @param  int  $product_id  Parent product ID.
     * @param  int  $variation_id  Variation ID.
     * @return string
     */
    private static function product_image($product_id, $variation_id)
    {
        $product = wc_get_product($variation_id ?: $product_id);
        $image_id = $product ? (int) $product->get_image_id() : 0;
        if ($image_id <= 0) {
            $parent = wc_get_product($product_id);
            $image_id = $parent ? (int) $parent->get_image_id() : 0;
        }
        if ($image_id <= 0 || !function_exists('wp_get_attachment_image_url')) {
            return '';
        }
        $url = wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail');

        return is_string($url) ? esc_url_raw($url) : '';
    }

    /**
     * Public subset of sanitized customer fields safe for event payloads.
     *
     * @param  array  $customer  Sanitized customer fields.
     * @return array
     */
    private static function public_customer($customer)
    {
        $contact = [];
        foreach (['name', 'phone', 'email', 'address', 'city', 'postcode', 'country'] as $field) {
            if (isset($customer[$field]) && is_string($customer[$field])) {
                $contact[$field] = $customer[$field];
            }
        }

        return $contact;
    }

    private static function sanitize_customer($customer)
    {
        if (!is_array($customer)) {
            return [];
        }
        $safe = [];
        foreach (['name', 'phone', 'email', 'address', 'city', 'postcode', 'country'] as $field) {
            if (isset($customer[$field]) && is_scalar($customer[$field])) {
                $safe[$field] = substr(trim(sanitize_text_field((string) $customer[$field])), 0, $field === 'address' ? 500 : 190);
            }
        }
        if (isset($customer['anonymous_id'])) {
            $safe['anonymous_id'] = substr(sanitize_text_field((string) $customer['anonymous_id']), 0, 128);
        }

        return $safe;
    }

    private static function sanitize_items($items)
    {
        $safe = [];
        foreach (array_slice((array) $items, 0, 50) as $item) {
            if (is_array($item)) {
                $safe[] = [
                    'product_id' => max(0, (int) ($item['product_id'] ?? 0)),
                    'variation_id' => max(0, (int) ($item['variation_id'] ?? 0)),
                    'quantity' => max(1, min(999, (int) ($item['quantity'] ?? 1))),
                ];
            }
        }

        return $safe;
    }

    private static function allow_capture_request($subject)
    {
        if (!apply_filters('adoology_allow_capture_request', true, $subject, self::client_ip())) {
            return false;
        }

        return self::consume_rate_limit((string) $subject, self::CAPTURE_LIMIT) &&
            self::consume_rate_limit('global', self::GLOBAL_CAPTURE_LIMIT);
    }

    private static function consume_rate_limit($subject, $limit)
    {
        $key = 'adoology_rate_' . hash('sha256', $subject . '|' . (int) floor(time() / MINUTE_IN_SECONDS));
        if (function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache()) {
            if (wp_cache_add($key, 1, 'adoology_capture', MINUTE_IN_SECONDS + 5)) {
                return true;
            }
            $count = wp_cache_incr($key, 1, 'adoology_capture');

            return is_int($count) && $count <= $limit;
        }

        global $wpdb;
        $option = '_transient_' . $key;
        if (add_option($option, 1, '', false)) {
            add_option('_transient_timeout_' . $key, time() + MINUTE_IN_SECONDS + 5, '', false);

            return true;
        }
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $count = (int) get_option($option, 0);
            if ($count >= $limit) {
                return false;
            }
            $updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                (string) ($count + 1),
                $option,
                (string) $count
            ));
            if ($updated) {
                return true;
            }
        }

        return false;
    }

    private static function load_woocommerce_cart()
    {
        if (!function_exists('WC') || !function_exists('wc_load_cart')) {
            return;
        }
        $woocommerce = WC();
        /** @var mixed $session */
        $session = $woocommerce->session;
        /** @var mixed $cart */
        $cart = $woocommerce->cart;
        if (!$session || !$cart) {
            wc_load_cart();
        }
    }

    private static function purge_checkout_rows($group, $cutoff)
    {
        global $wpdb;

        $table = Database::incomplete_table();
        $condition = $group === 'expired'
            ? "status = 'expired' AND expires_at < %s"
            : "status IN ('converted','recovered') AND updated_at < %s";
        $has_more = false;
        for ($batch = 0; $batch < self::LIFECYCLE_MAX_BATCHES; $batch++) {
            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT id, checkout_id, status, customer_data FROM {$table} WHERE {$condition} ORDER BY id ASC LIMIT %d",
                $cutoff,
                self::LIFECYCLE_BATCH_SIZE
            ), ARRAY_A);
            foreach ($rows as $row) {
                $customer = self::customer_payload((string) $row['customer_data'], (string) $row['checkout_id']);
                $anonymous_id = is_string($customer['anonymous_id'] ?? null) ? $customer['anonymous_id'] : '';
                if ($anonymous_id !== '' && !self::delete_events_for_checkout($anonymous_id, (string) $row['checkout_id'])) {
                    continue;
                }
                $wpdb->delete($table, ['id' => (int) $row['id'], 'status' => $row['status']], ['%d', '%s']);
            }
            $has_more = count($rows) === self::LIFECYCLE_BATCH_SIZE;
            if (!$has_more) {
                break;
            }
        }

        return $has_more;
    }

    private static function delete_events_for_checkout($anonymous_id, $checkout_id)
    {
        global $wpdb;

        $table = Database::events_table();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, event_id, payload FROM {$table} WHERE anonymous_id = %s",
            $anonymous_id
        ), ARRAY_A);
        foreach ($rows as $row) {
            $decrypted = Crypto::decrypt((string) $row['payload'], 'adoology_event_' . $row['event_id']);
            if (is_wp_error($decrypted)) {
                return false;
            }
            $event = json_decode($decrypted, true);
            $event_checkout_id = is_array($event) && is_array($event['properties'] ?? null)
                ? (string) ($event['properties']['checkout_id'] ?? '')
                : '';
            if ($event_checkout_id === $checkout_id) {
                // The terminal expiry event must reach the backend before retention cleanup.
                if (is_array($event) && ($event['name'] ?? '') === 'checkout.expired') {
                    continue;
                }
                $wpdb->delete($table, ['id' => (int) $row['id']], ['%d']);
            }
        }

        return true;
    }

    private static function is_same_origin($request)
    {
        $source = (string) $request->get_header('Origin');
        if ($source === '') {
            $source = (string) $request->get_header('Referer');
        }
        if ($source === '') {
            return false;
        }
        $actual = wp_parse_url($source);
        $expected = wp_parse_url(home_url('/'));
        if (!is_array($actual) || !is_array($expected)) {
            return false;
        }
        foreach (['scheme', 'host', 'port'] as $part) {
            if (strtolower((string) ($actual[$part] ?? '')) !== strtolower((string) ($expected[$part] ?? ''))) {
                return false;
            }
        }

        return true;
    }

    private static function capture_signature($anonymous_id, $checkout_id, $session_id, $context, $expires)
    {
        $message = $anonymous_id . '|' . $checkout_id . '|' . $session_id . '|' . hash('sha256', $context) . '|' . (int) $expires;

        return (int) $expires . '.' . hash_hmac('sha256', $message, wp_salt('nonce'));
    }

    private static function verify_capture_token($token, $anonymous_id, $checkout_id, $session_id, $context)
    {
        $parts = explode('.', (string) $token, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || (int) $parts[0] < time() || (int) $parts[0] > time() + 3 * HOUR_IN_SECONDS) {
            return false;
        }

        return hash_equals(self::capture_signature($anonymous_id, $checkout_id, $session_id, $context, (int) $parts[0]), $token);
    }

    private static function privacy_rows($email, $page, $limit = 100, $after_id = 0)
    {
        global $wpdb;

        if ($limit > 0 && $after_id > 0) {
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . Database::incomplete_table() . " WHERE customer_data <> '' AND id > %d ORDER BY id ASC LIMIT %d",
                $after_id,
                $limit
            ), ARRAY_A);
        } elseif ($limit > 0) {
            $rows = $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . Database::incomplete_table() . " WHERE customer_data <> '' ORDER BY id ASC LIMIT %d OFFSET %d",
                $limit,
                max(0, ((int) $page - 1) * $limit)
            ), ARRAY_A);
        } else {
            $rows = $wpdb->get_results('SELECT * FROM ' . Database::incomplete_table() . " WHERE customer_data <> '' ORDER BY id ASC", ARRAY_A);
        }
        $matches = [];
        foreach ($rows as $row) {
            $decrypted = Crypto::decrypt($row['customer_data'], 'adoology_checkout_' . $row['checkout_id']);
            $customer = is_wp_error($decrypted) ? null : json_decode($decrypted, true);
            if (is_array($customer) && !empty($customer['email']) && strtolower($customer['email']) === strtolower($email)) {
                $matches[] = ['row' => $row, 'customer' => $customer];
            }
        }

        $last = end($rows);

        return [
            'rows' => $matches,
            'done' => $limit === 0 || count($rows) < $limit,
            'last_id' => is_array($last) ? (int) $last['id'] : $after_id,
        ];
    }

    private static function events_for_anonymous($anonymous_id)
    {
        global $wpdb;

        if ($anonymous_id === '') {
            return [];
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT event_id, payload FROM ' . Database::events_table() . ' WHERE anonymous_id = %s ORDER BY id ASC',
            $anonymous_id
        ), ARRAY_A);
        $events = [];
        foreach ($rows as $row) {
            $decrypted = Crypto::decrypt($row['payload'], 'adoology_event_' . $row['event_id']);
            $decoded = is_wp_error($decrypted) ? null : json_decode($decrypted, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }

        return $events;
    }

    private static function is_uuid($value)
    {
        return is_string($value) && (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/Di', $value);
    }

    private static function current_url()
    {
        $path = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';

        return home_url($path);
    }

    private static function set_cookie($name, $value, $expires)
    {
        if (!headers_sent()) {
            setcookie($name, $value, [
                'expires' => $expires,
                'path' => COOKIEPATH ?: '/',
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE[$name] = $value;
    }
}
