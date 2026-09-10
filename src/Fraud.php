<?php

/**
 * Local bot, velocity, duplicate-order, and fraud protection.
 */

namespace Adoology;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use WC_Order;
use WP_Error;
use WP_REST_Request;

if (!defined('ABSPATH')) {
    exit;
}

class Fraud
{
    /**
     * Request-scoped assessment for classic checkout.
     *
     * @var array|null
     */
    private static $assessment = null;

    /**
     * Register checkout protection hooks.
     */
    public static function register()
    {
        add_action('woocommerce_after_order_notes', [self::class, 'render_honeypot']);
        add_action('woocommerce_after_checkout_validation', [self::class, 'validate_checkout'], 20, 2);
        add_action('woocommerce_checkout_create_order', [self::class, 'attach_to_order'], 30, 2);
        add_action('woocommerce_checkout_order_processed', [self::class, 'apply_order_action'], 40, 3);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [self::class, 'protect_store_api'], 30, 2);
        add_action('woocommerce_store_api_checkout_order_processed', [self::class, 'apply_store_api_action'], 40, 1);
        add_filter('woocommerce_order_needs_payment', [self::class, 'held_order_needs_payment'], 10, 2);
    }

    /**
     * Render an invisible field bots often fill.
     */
    public static function render_honeypot()
    {
        echo '<p style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">';
        echo '<label for="adoology_website">' . esc_html__('Website', 'adoology-connector') . '</label>';
        echo '<input type="text" id="adoology_website" name="adoology_website" value="" tabindex="-1" autocomplete="off" />';
        echo '</p>';
    }

    /**
     * Block high-risk classic checkout submissions.
     *
     * @param  array  $data  Checkout data.
     * @param  WP_Error  $errors  Checkout errors.
     */
    public static function validate_checkout($data, $errors)
    {
        if (!self::enabled()) {
            return;
        }
        $data['honeypot'] = isset($_POST['adoology_website']) ? sanitize_text_field(wp_unslash($_POST['adoology_website'])) : '';
        self::$assessment = self::evaluate($data, self::cart_product_ids(), true);
        if (self::$assessment['action'] === 'block') {
            $errors->add('adoology_risk_blocked', self::rejection_message(self::$assessment));
        }
    }

    /**
     * Save risk result before order creation.
     *
     * @param  WC_Order  $order  Order.
     * @param  array  $data  Checkout data.
     */
    public static function attach_to_order($order, $data)
    {
        if (!$order instanceof WC_Order || !self::enabled()) {
            return;
        }
        $assessment = self::$assessment ?: self::evaluate($data, self::cart_product_ids(), false);
        self::store_order_assessment($order, $assessment);
    }

    /**
     * Hold or flag classic order.
     *
     * @param  int  $order_id  Order ID.
     * @param  array  $posted  Checkout data.
     * @param  WC_Order  $order  Order.
     */
    public static function apply_order_action($order_id, $posted, $order)
    {
        if ($order instanceof WC_Order) {
            self::enforce_order_assessment($order);
        }
    }

    /**
     * Assess Store API checkout using server request values.
     *
     * @param  WC_Order  $order  Order under construction.
     * @param  WP_REST_Request  $request  Store API request.
     */
    public static function protect_store_api($order, $request)
    {
        if (!$order instanceof WC_Order || !self::enabled()) {
            return;
        }
        $billing = is_object($request) ? $request->get_param('billing_address') : [];
        $billing = is_array($billing) ? $billing : [];
        $data = [
            'billing_phone' => $billing['phone'] ?? '',
            'billing_email' => $billing['email'] ?? '',
            'honeypot' => '',
        ];
        $assessment = self::evaluate($data, self::order_product_ids($order), true);
        if ($assessment['action'] === 'block' && class_exists('Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException')) {
            throw new RouteException(
                'adoology_risk_blocked',
                self::rejection_message($assessment),
                403
            );
        }
        self::store_order_assessment($order, $assessment);
    }

    /**
     * Hold or flag Store API order.
     *
     * @param  WC_Order  $order  Order.
     */
    public static function apply_store_api_action($order)
    {
        if ($order instanceof WC_Order) {
            self::enforce_order_assessment($order);
        }
    }

    /**
     * Calculate deterministic risk score and action.
     *
     * @param  array  $data  Customer/request data.
     * @param  array  $product_ids  Product IDs.
     * @param  bool  $count_attempt  Record submission velocity.
     * @return array
     */
    public static function evaluate($data, $product_ids = [], $count_attempt = true)
    {
        $score = 0;
        $signals = [];
        $raw_phone = sanitize_text_field((string) ($data['billing_phone'] ?? $data['phone'] ?? ''));
        $phone = self::normalize_phone($raw_phone);
        $email = sanitize_email((string) ($data['billing_email'] ?? $data['email'] ?? ''));
        $agent = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

        if (!empty($data['honeypot'])) {
            $score += 100;
            $signals[] = 'honeypot';
        }
        if ($agent === '' || preg_match('/(?:curl|wget|python-requests|scrapy|headless|phantomjs|selenium|httpclient)/i', $agent)) {
            $score += 35;
            $signals[] = 'bot_user_agent';
        }
        if ($phone !== '' && strlen($phone) < 7) {
            $score += 20;
            $signals[] = 'invalid_phone';
        }
        if (($data['billing_email'] ?? $data['email'] ?? '') !== '' && $email === '') {
            $score += 20;
            $signals[] = 'invalid_email';
        }

        $attempts = self::attempt_count($count_attempt);
        $limit = min(100, max(2, (int) Options::get('adoology_fraud_rate_limit', 5)));
        if ($attempts > $limit) {
            $score += min(50, 15 + ($attempts - $limit) * 5);
            $signals[] = 'ip_velocity';
        }

        if (($phone !== '' || $email !== '') && self::has_blocking_duplicate($raw_phone, $phone, $email)) {
            $score = 100;
            $signals[] = 'duplicate_order_block';
        } elseif (($phone !== '' || $email !== '') && self::has_recent_duplicate($raw_phone, $phone, $email, $product_ids)) {
            $score += 45;
            $signals[] = 'duplicate_order';
        }

        $score = min(100, $score);
        $flag = min(100, max(1, (int) Options::get('adoology_fraud_flag_threshold', 30)));
        $hold = min(100, max($flag, (int) Options::get('adoology_fraud_hold_threshold', 60)));
        $block = min(100, max($hold, (int) Options::get('adoology_fraud_block_threshold', 90)));
        $action = $score >= $block ? 'block' : ($score >= $hold ? 'hold' : ($score >= $flag ? 'flag' : 'allow'));

        return [
            'score' => $score,
            'action' => $action,
            'signals' => array_values(array_unique($signals)),
        ];
    }

    /**
     * Whether protection is enabled.
     *
     * @return bool
     */
    public static function enabled()
    {
        return Options::get('adoology_fraud_enabled', 'yes') === 'yes';
    }

    /**
     * Customer-facing rejection message for a blocked assessment.
     *
     * @param  array  $assessment  Risk assessment.
     * @return string
     */
    public static function rejection_message($assessment)
    {
        if (in_array('duplicate_order_block', (array) ($assessment['signals'] ?? []), true)) {
            return __('You recently placed an order. Please wait a few minutes before placing another.', 'adoology-connector');
        }

        return __('We could not accept this order. Please contact the store for assistance.', 'adoology-connector');
    }

    /**
     * Store an assessment created by a non-cart order flow.
     *
     * @param  WC_Order  $order  Order.
     * @param  array  $assessment  Assessment.
     */
    public static function store_order_assessment($order, $assessment)
    {
        $order->update_meta_data('_adoology_risk_score', (int) $assessment['score']);
        $order->update_meta_data('_adoology_risk_action', sanitize_key($assessment['action']));
        $order->update_meta_data('_adoology_risk_signals', wp_json_encode($assessment['signals']));
        $order->update_meta_data('_adoology_normalized_phone', self::normalize_phone($order->get_billing_phone()));
    }

    /**
     * Keep risk-held on-hold orders inside the payment branch.
     *
     * WooCommerce excludes on-hold from needs-payment statuses, which would
     * send held orders through payment_complete() and mark them paid without
     * any collection. Held orders with a positive total must still route
     * through the selected gateway.
     *
     * @param  bool  $needs_payment  Whether the order needs payment.
     * @param  WC_Order  $order  Order.
     * @return bool
     */
    public static function held_order_needs_payment($needs_payment, $order)
    {
        if ($needs_payment || !self::enabled() || !$order instanceof WC_Order) {
            return $needs_payment;
        }
        if (!$order->has_status('on-hold') || (float) $order->get_total() <= 0) {
            return $needs_payment;
        }

        return $order->get_meta('_adoology_risk_action', true) === 'hold';
    }

    /**
     * Apply stored risk action and emit telemetry.
     *
     * @param  WC_Order  $order  Order.
     */
    public static function enforce_order_assessment($order)
    {
        if (!self::enabled()) {
            return;
        }
        if ($order->get_meta('_adoology_risk_event_sent', true) === 'yes') {
            return;
        }
        $score = (int) $order->get_meta('_adoology_risk_score', true);
        $action = (string) $order->get_meta('_adoology_risk_action', true);
        $signals = json_decode((string) $order->get_meta('_adoology_risk_signals', true), true);
        $signals = is_array($signals) ? $signals : [];
        if ($action === 'hold' && !in_array($order->get_status(), ['cancelled', 'refunded', 'completed'], true)) {
            $order->update_status('on-hold', __('Held by Adoology local risk assessment.', 'adoology-connector'));
        } elseif ($action === 'flag') {
            $order->add_order_note(__('Flagged by Adoology local risk assessment.', 'adoology-connector'));
        }

        $identity = IncompleteOrders::identity_for_checkout((string) $order->get_meta('_adoology_checkout_id', true));
        $queued = Events::enqueue('risk.assessed', $identity['anonymous_id'], $identity['session_id'], [
            'order_id' => $order->get_id(),
            'risk_score' => $score,
            'action' => $action ?: 'allow',
            'signals' => $signals,
        ], IncompleteOrders::request_context());
        if (!is_wp_error($queued)) {
            $order->update_meta_data('_adoology_risk_event_sent', 'yes');
            $order->save_meta_data();
        }
    }

    private static function attempt_count($increment)
    {
        $subject = IncompleteOrders::client_identifier();
        $key = 'adoology_attempt_' . hash_hmac('sha256', $subject, wp_salt('nonce'));
        $count = (int) get_transient($key);
        if ($increment) {
            $count++;
            set_transient($key, $count, 10 * MINUTE_IN_SECONDS);
        }

        return $count;
    }

    private static function has_recent_duplicate($raw_phone, $phone, $email, $product_ids)
    {
        if (!function_exists('wc_get_orders')) {
            return false;
        }
        $minutes = min(1440, max(5, (int) Options::get('adoology_duplicate_window_minutes', 60)));
        $base_args = [
            'limit' => 10,
            'return' => 'objects',
            'status' => ['pending', 'processing', 'on-hold', 'completed'],
            'date_created' => '>' . (time() - $minutes * MINUTE_IN_SECONDS),
        ];
        $orders = [];
        foreach (array_values(array_unique(array_filter([$raw_phone, $phone]))) as $phone_value) {
            $args = $base_args;
            $args['billing_phone'] = $phone_value;
            foreach (wc_get_orders($args) as $order) {
                $orders[$order->get_id()] = $order;
            }
        }
        if ($email !== '') {
            $args = $base_args;
            $args['billing_email'] = $email;
            foreach (wc_get_orders($args) as $order) {
                $orders[$order->get_id()] = $order;
            }
        }
        if ($phone !== '') {
            $args = $base_args;
            $args['meta_query'] = [['key' => '_adoology_normalized_phone', 'value' => $phone, 'compare' => '=']];
            foreach (wc_get_orders($args) as $order) {
                $orders[$order->get_id()] = $order;
            }
        }
        if (empty($orders)) {
            return false;
        }
        if (empty($product_ids)) {
            return true;
        }
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if (in_array((int) $item->get_product_id(), array_map('intval', $product_ids), true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the same contact placed any recent order inside the hard-block window.
     *
     * @param  string  $raw_phone  Raw billing phone.
     * @param  string  $phone  Normalized billing phone.
     * @param  string  $email  Billing email.
     * @return bool
     */
    private static function has_blocking_duplicate($raw_phone, $phone, $email)
    {
        if (!function_exists('wc_get_orders')) {
            return false;
        }
        $minutes = (int) Options::get('adoology_duplicate_block_minutes', 5);
        if ($minutes <= 0) {
            return false;
        }
        $minutes = min(1440, $minutes);
        $base_args = [
            'limit' => 1,
            'return' => 'ids',
            'status' => ['pending', 'processing', 'on-hold', 'completed'],
            'date_created' => '>' . (time() - $minutes * MINUTE_IN_SECONDS),
        ];
        foreach (array_values(array_unique(array_filter([$raw_phone, $phone]))) as $phone_value) {
            $args = $base_args;
            $args['billing_phone'] = $phone_value;
            if (!empty(wc_get_orders($args))) {
                return true;
            }
        }
        if ($email !== '') {
            $args = $base_args;
            $args['billing_email'] = $email;
            if (!empty(wc_get_orders($args))) {
                return true;
            }
        }
        if ($phone !== '') {
            $args = $base_args;
            $args['meta_query'] = [['key' => '_adoology_normalized_phone', 'value' => $phone, 'compare' => '=']];
            if (!empty(wc_get_orders($args))) {
                return true;
            }
        }

        return false;
    }

    private static function cart_product_ids()
    {
        $ids = [];
        if (function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_cart() as $item) {
                $ids[] = (int) $item['product_id'];
            }
        }

        return array_values(array_unique($ids));
    }

    private static function order_product_ids($order)
    {
        $ids = [];
        foreach ($order->get_items() as $item) {
            $ids[] = (int) $item->get_product_id();
        }

        return array_values(array_unique($ids));
    }

    private static function normalize_phone($phone)
    {
        return preg_replace('/[^0-9+]/', '', $phone);
    }
}
