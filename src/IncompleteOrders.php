<?php

/**
 * Incomplete checkout tracking for native checkout and landing-page forms.
 */

namespace Adoology;

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
    const LIFECYCLE_HOOK = 'adoology_incomplete_order_lifecycle';

    const COOKIE_ANON = 'adoology_anonymous_id';

    const COOKIE_CHECKOUT = 'adoology_checkout_id';

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
        add_action(self::LIFECYCLE_HOOK, [self::class, 'advance_lifecycle']);
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
     */
    public static function enqueue_tracker($flow, $extra = [])
    {
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
            'flow' => sanitize_key($flow),
            'landingPage' => esc_url_raw(self::current_url()),
            'trackingEnabled' => Options::get('adoology_tracking_enabled', 'no') === 'yes',
            'data' => $extra,
        ]);
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
        if (!self::allow_capture_request()) {
            return new WP_Error('adoology_capture_limited', __('Too many checkout updates.', 'adoology-connector'), ['status' => 429]);
        }

        $params = $request->get_json_params();
        if (!is_array($params)) {
            return new WP_Error('adoology_capture_invalid', __('Invalid checkout data.', 'adoology-connector'), ['status' => 400]);
        }
        $checkout_id = isset($params['checkout_id']) ? sanitize_text_field($params['checkout_id']) : '';
        $anonymous_id = isset($params['anonymous_id']) ? sanitize_text_field($params['anonymous_id']) : '';
        $capture_token = isset($params['capture_token']) ? sanitize_text_field($params['capture_token']) : '';
        if (!self::is_uuid($checkout_id) || !self::is_uuid($anonymous_id) || !self::verify_capture_token($capture_token, $anonymous_id, $checkout_id)) {
            return new WP_Error('adoology_capture_invalid', __('Invalid checkout identity.', 'adoology-connector'), ['status' => 400]);
        }

        $result = self::store_snapshot($checkout_id, [
            'anonymous_id' => $anonymous_id,
            'session_id' => $params['session_id'] ?? '',
            'flow' => $params['flow'] ?? 'checkout',
            'product_id' => $params['product_id'] ?? 0,
            'variation_id' => $params['variation_id'] ?? 0,
            'quantity' => $params['quantity'] ?? 1,
            'value_minor' => $params['value_minor'] ?? 0,
            'currency' => $params['currency'] ?? get_woocommerce_currency(),
            'customer' => isset($params['customer']) && is_array($params['customer']) ? $params['customer'] : [],
            'landing_page' => $params['landing_page'] ?? '',
            'form_stage' => $params['form_stage'] ?? 'started',
            'items' => isset($params['items']) && is_array($params['items']) ? $params['items'] : [],
        ]);
        if (is_wp_error($result)) {
            return $result;
        }

        return new WP_REST_Response(['stored' => true], 202);
    }

    /**
     * Issue a short-lived token bound to browser-generated identities.
     *
     * @param  WP_REST_Request  $request  Request.
     * @return WP_REST_Response|WP_Error
     */
    public static function issue_capture_token($request)
    {
        $enabled = Options::get('adoology_tracking_enabled', 'no') === 'yes' || Options::get('adoology_order_form_enabled', 'yes') === 'yes';
        if (!$enabled || !self::is_same_origin($request) || !self::allow_capture_request()) {
            return new WP_Error('adoology_capture_forbidden', __('Checkout capture request was rejected.', 'adoology-connector'), ['status' => 403]);
        }
        $anonymous_id = wp_generate_uuid4();
        $checkout_id = wp_generate_uuid4();
        $expires = time() + 2 * HOUR_IN_SECONDS;
        $response = new WP_REST_Response([
            'anonymous_id' => $anonymous_id,
            'checkout_id' => $checkout_id,
            'session_id' => $anonymous_id,
            'expires' => $expires,
            'token' => self::capture_signature($anonymous_id, $checkout_id, $expires),
        ], 200);
        $response->header('Cache-Control', 'no-store, private, max-age=0');
        $response->header('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Store or update one checkout snapshot.
     *
     * @param  string  $checkout_id  Checkout UUID.
     * @param  array  $data  Snapshot data.
     * @return true|WP_Error
     */
    public static function store_snapshot($checkout_id, $data)
    {
        global $wpdb;

        if (!self::is_uuid($checkout_id)) {
            return new WP_Error('adoology_checkout_invalid', __('Invalid checkout identifier.', 'adoology-connector'));
        }

        $table = Database::incomplete_table();
        $existing = $wpdb->get_row($wpdb->prepare("SELECT id, status, form_stage FROM {$table} WHERE checkout_id = %s", $checkout_id), ARRAY_A);
        $tracking = Options::get('adoology_tracking_enabled', 'no') === 'yes';
        $customer = $tracking ? self::sanitize_customer($data['customer'] ?? []) : [];
        if ($tracking) {
            $customer['anonymous_id'] = substr(sanitize_text_field((string) ($data['anonymous_id'] ?? '')), 0, 128);
            $customer['items'] = self::sanitize_items($data['items'] ?? []);
        }
        $json = $tracking ? wp_json_encode($customer) : '';
        $encrypted = $json && $customer ? Crypto::encrypt($json, 'adoology_checkout_' . $checkout_id) : '';
        if (is_wp_error($encrypted)) {
            return $encrypted;
        }

        $now = gmdate('Y-m-d H:i:s');
        $expires = gmdate('Y-m-d H:i:s', time() + max(1, (int) Options::get('adoology_incomplete_expire_days', 7)) * DAY_IN_SECONDS);
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
            if (in_array($existing['status'], ['converted', 'recovered', 'expired'], true)) {
                return true;
            }
            $stages = ['started' => 1, 'details' => 2, 'leaving' => 3, 'submitted' => 4, 'completed' => 5];
            if (($stages[$existing['form_stage']] ?? 0) > ($stages[$row['form_stage']] ?? 0)) {
                $row['form_stage'] = $existing['form_stage'];
            }
            $wpdb->update($table, $row, ['id' => (int) $existing['id']]);
            if ($tracking && self::customer_changed((string) ($existing['customer_data'] ?? ''), $checkout_id, $customer)) {
                Events::enqueue('checkout.updated', (string) ($data['anonymous_id'] ?? self::anonymous_id()), $row['session_id'], [
                    'checkout_id' => $checkout_id,
                    'flow' => $row['flow'],
                    'product_id' => $row['product_id'],
                    'quantity' => $row['quantity'],
                    'value_minor' => $row['value_minor'],
                    'currency' => $row['currency'],
                    'form_stage' => $row['form_stage'],
                    'customer' => self::public_customer($customer),
                ], self::request_context());
            }

            return true;
        }

        $row['checkout_id'] = $checkout_id;
        $row['status'] = 'started';
        $row['created_at'] = $now;
        $inserted = $wpdb->insert($table, $row);
        if (!$inserted) {
            $race = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE checkout_id = %s", $checkout_id));
            if ($race) {
                unset($row['checkout_id'], $row['status'], $row['created_at']);
                $wpdb->update($table, $row, ['id' => (int) $race]);

                return true;
            }

            return new WP_Error('adoology_checkout_store_failed', __('Could not store incomplete checkout.', 'adoology-connector'));
        }

        if ($tracking) {
            Events::enqueue('checkout.started', (string) ($data['anonymous_id'] ?? self::anonymous_id()), $row['session_id'], [
                'checkout_id' => $checkout_id,
                'flow' => $row['flow'],
                'product_id' => $row['product_id'],
                'quantity' => $row['quantity'],
                'value_minor' => $row['value_minor'],
                'currency' => $row['currency'],
                'form_stage' => $row['form_stage'],
                'landing_page' => $row['landing_page'],
            ], self::request_context());
        }

        return true;
    }

    /**
     * Mark classic checkout conversion.
     *
     * @param  int  $order_id  Order ID.
     * @param  array  $posted  Posted data.
     * @param  WC_Order  $order  Order.
     */
    public static function checkout_completed($order_id, $posted, $order)
    {
        $checkout_id = isset($posted['_adoology_checkout_id']) ? sanitize_text_field($posted['_adoology_checkout_id']) : self::checkout_id();
        self::mark_complete($checkout_id, $order_id, $order);
    }

    /**
     * Mark Store API checkout conversion.
     *
     * @param  WC_Order  $order  Order.
     */
    public static function store_api_checkout_completed($order)
    {
        if ($order instanceof WC_Order) {
            $checkout_id = (string) $order->get_meta('_adoology_checkout_id', true);
            self::mark_complete($checkout_id ?: self::checkout_id(), $order->get_id(), $order);
        }
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
        $extensions = $request->get_param('extensions');
        $extension = is_array($extensions) && isset($extensions['adoology']) && is_array($extensions['adoology']) ? $extensions['adoology'] : [];
        $checkout_id = sanitize_text_field((string) ($extension['checkout_id'] ?? ''));
        if (self::is_uuid($checkout_id)) {
            $order->update_meta_data('_adoology_checkout_id', $checkout_id);
        }
    }

    /**
     * Capture billing details saved by the WooCommerce Blocks cart API.
     *
     * @param  WC_Customer  $customer  Customer updated by the Store API.
     * @param  WP_REST_Request  $request  Store API request.
     */
    public static function store_api_cart_updated($customer, $request)
    {
        if (Options::get('adoology_tracking_enabled', 'no') !== 'yes' || !$customer instanceof WC_Customer) {
            return;
        }

        $identity = self::identity();
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

        self::store_snapshot($identity['checkout_id'], [
            'anonymous_id' => $identity['anonymous_id'],
            'session_id' => $identity['session_id'],
            'flow' => 'checkout',
            'product_id' => $primary_item['product_id'] ?? 0,
            'variation_id' => $primary_item['variation_id'] ?? 0,
            'quantity' => $primary_item['quantity'] ?? 1,
            'value_minor' => $cart ? self::to_minor($cart->get_total('edit')) : 0,
            'currency' => get_woocommerce_currency(),
            'customer' => [
                'name' => trim($customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name()),
                'phone' => $customer->get_billing_phone(),
                'email' => $customer->get_billing_email(),
                'address' => trim($customer->get_billing_address_1() . ' ' . $customer->get_billing_address_2()),
                'city' => $customer->get_billing_city(),
                'postcode' => $customer->get_billing_postcode(),
                'country' => $customer->get_billing_country(),
            ],
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
     */
    public static function mark_complete($checkout_id, $order_id, $order = null)
    {
        global $wpdb;

        if (!self::is_uuid($checkout_id)) {
            return;
        }
        $table = Database::incomplete_table();
        $row = $wpdb->get_row($wpdb->prepare("SELECT id, status, session_id FROM {$table} WHERE checkout_id = %s", $checkout_id), ARRAY_A);
        if (!$row || in_array($row['status'], ['converted', 'recovered'], true)) {
            return;
        }
        $status = in_array($row['status'], ['incomplete', 'submitting_recovery'], true) ? 'recovered' : 'converted';
        $updated = $wpdb->update($table, [
            'status' => $status,
            'order_id' => (int) $order_id,
            'form_stage' => 'completed',
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ], ['id' => (int) $row['id'], 'status' => $row['status']]);
        if (!$updated) {
            return;
        }

        if ($order instanceof WC_Order) {
            $order->update_meta_data('_adoology_checkout_id', $checkout_id);
            $order->save_meta_data();
        }
        if (Options::get('adoology_tracking_enabled', 'no') === 'yes') {
            Events::enqueue('checkout.' . $status, self::anonymous_id(), (string) $row['session_id'], [
                'checkout_id' => $checkout_id,
                'order_id' => (int) $order_id,
                'status' => $status,
            ], self::request_context());
        }
        self::rotate_checkout_id();
    }

    /**
     * Advance idle checkouts to incomplete and expired states.
     */
    public static function advance_lifecycle()
    {
        global $wpdb;

        $table = Database::incomplete_table();
        $timeout = max(5, (int) Options::get('adoology_incomplete_timeout_minutes', 30)) * MINUTE_IN_SECONDS;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = CASE WHEN status = 'submitting_recovery' THEN 'incomplete' ELSE 'started' END, updated_at = %s WHERE status IN ('submitting','submitting_recovery') AND order_id = 0 AND updated_at < %s",
            gmdate('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS)
        ));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, checkout_id, session_id, flow, product_id, variation_id, quantity, value_minor, currency, form_stage, customer_data FROM {$table} WHERE status = 'started' AND last_activity_at < %s LIMIT 100",
            gmdate('Y-m-d H:i:s', time() - $timeout)
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

        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'expired', updated_at = %s, customer_data = '' WHERE status IN ('started','incomplete') AND expires_at < %s",
            gmdate('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s')
        ));
        $retention = max(1, (int) Options::get('adoology_incomplete_expire_days', 7)) * DAY_IN_SECONDS;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET customer_data = '', updated_at = %s WHERE status IN ('converted','recovered','expired') AND customer_data <> '' AND updated_at < %s",
            gmdate('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s', time() - $retention)
        ));
    }

    /**
     * Atomically claim checkout before creating an order.
     *
     * @param  string  $checkout_id  Checkout UUID.
     * @return array|WP_Error
     */
    public static function claim_submission($checkout_id)
    {
        global $wpdb;

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
    public static function identity()
    {
        return [
            'anonymous_id' => self::anonymous_id(),
            'checkout_id' => self::checkout_id(),
            'session_id' => function_exists('WC') && WC()->session ? (string) WC()->session->get_customer_id() : self::anonymous_id(),
        ];
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

        $matches = self::privacy_rows($email, 1, 0);
        $removed = false;
        foreach ($matches['rows'] as $match) {
            $anonymous_id = (string) ($match['customer']['anonymous_id'] ?? '');
            if ($anonymous_id !== '') {
                $wpdb->delete(Database::events_table(), ['anonymous_id' => $anonymous_id], ['%s']);
            }
            $wpdb->delete(Database::incomplete_table(), ['id' => (int) $match['row']['id']], ['%d']);
            $removed = true;
        }

        return [
            'items_removed' => $removed,
            'items_retained' => $removed,
            'messages' => $removed ? [__('Local data was erased. Data already delivered to Adoology must be erased from the connected workspace separately.', 'adoology-connector')] : [],
            'done' => true,
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
     * Minimized contact snapshot for backend recovery workflows.
     *
     * @param  string  $payload  Encrypted customer data.
     * @param  string  $checkout_id  Checkout UUID.
     * @return array
     */
    private static function contact_payload($payload, $checkout_id)
    {
        if ($payload === '') {
            return [];
        }
        $decrypted = Crypto::decrypt($payload, 'adoology_checkout_' . $checkout_id);
        $data = is_wp_error($decrypted) ? null : json_decode($decrypted, true);
        if (!is_array($data)) {
            return [];
        }
        $contact = [];
        foreach (['name', 'phone', 'email', 'address', 'city', 'postcode', 'country'] as $field) {
            if (isset($data[$field]) && is_string($data[$field]) && $data[$field] !== '') {
                $contact[$field] = mb_substr($data[$field], 0, $field === 'address' ? 500 : 190);
            }
        }

        return $contact;
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
        $properties = [
            'checkout_id' => $row['checkout_id'],
            'flow' => $row['flow'],
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => (int) $row['quantity'],
            'value_minor' => (int) $row['value_minor'],
            'currency' => $row['currency'],
            'form_stage' => $row['form_stage'],
            'customer' => self::contact_payload((string) ($row['customer_data'] ?? ''), $row['checkout_id']),
        ];
        $product = wc_get_product($variation_id ?: $product_id);
        if ($product) {
            $product_name = sanitize_text_field((string) $product->get_name());
            $product_name = function_exists('mb_substr') ? mb_substr($product_name, 0, 190) : substr($product_name, 0, 190);
            if ($product_name !== '') {
                $properties['product_name'] = $product_name;
            }
        }

        return $properties;
    }

    /**
     * Whether freshly captured contact details add usable recovery data.
     *
     * @param  string  $stored  Encrypted stored customer data.
     * @param  string  $checkout_id  Checkout UUID.
     * @param  array  $incoming  Sanitized incoming customer fields.
     * @return bool
     */
    private static function customer_changed($stored, $checkout_id, $incoming)
    {
        $previous = self::contact_payload($stored, $checkout_id);
        $fresh = self::public_customer($incoming);
        if ($fresh === []) {
            return false;
        }
        foreach (['name', 'phone', 'email'] as $field) {
            if (isset($fresh[$field]) && ($previous[$field] ?? '') !== $fresh[$field]) {
                return true;
            }
        }

        return false;
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
            if (isset($customer[$field]) && is_string($customer[$field]) && $customer[$field] !== '') {
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
            if (isset($customer[$field]) && $customer[$field] !== '') {
                $safe[$field] = substr(sanitize_text_field((string) $customer[$field]), 0, $field === 'address' ? 500 : 190);
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

    private static function allow_capture_request()
    {
        global $wpdb;

        $key = 'adoology_capture_' . hash_hmac('sha256', self::client_ip(), wp_salt('nonce'));
        $count = (int) get_transient($key);
        if ($count >= 60) {
            return false;
        }
        $global = (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . Database::incomplete_table() . ' WHERE created_at >= %s',
            gmdate('Y-m-d H:i:s', time() - MINUTE_IN_SECONDS)
        ));
        if ($global >= 300) {
            return false;
        }
        set_transient($key, $count + 1, MINUTE_IN_SECONDS);

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

    private static function capture_signature($anonymous_id, $checkout_id, $expires)
    {
        $message = $anonymous_id . '|' . $checkout_id . '|' . (int) $expires;

        return (int) $expires . '.' . hash_hmac('sha256', $message, wp_salt('nonce'));
    }

    private static function verify_capture_token($token, $anonymous_id, $checkout_id)
    {
        $parts = explode('.', (string) $token, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[0]) || (int) $parts[0] < time() || (int) $parts[0] > time() + 3 * HOUR_IN_SECONDS) {
            return false;
        }

        return hash_equals(self::capture_signature($anonymous_id, $checkout_id, (int) $parts[0]), $token);
    }

    private static function privacy_rows($email, $page, $limit = 100)
    {
        global $wpdb;

        if ($limit > 0) {
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

        return ['rows' => $matches, 'done' => $limit === 0 || count($rows) < $limit];
    }

    private static function events_for_anonymous($anonymous_id)
    {
        global $wpdb;

        if ($anonymous_id === '') {
            return [];
        }
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT event_id, payload FROM ' . Database::events_table() . ' WHERE anonymous_id = %s ORDER BY id ASC LIMIT 100',
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

    private static function rotate_checkout_id()
    {
        self::set_cookie(self::COOKIE_CHECKOUT, wp_generate_uuid4(), time() + 7 * DAY_IN_SECONDS);
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
