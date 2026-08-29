<?php
/**
 * Local bot, velocity, duplicate-order, and fraud protection.
 *
 * @package Adoology_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

class Adoology_Fraud {

    /**
     * Request-scoped assessment for classic checkout.
     *
     * @var array|null
     */
    private static $assessment = null;

    /**
     * Register checkout protection hooks.
     */
    public static function register() {
        add_action('woocommerce_after_order_notes', array(__CLASS__, 'render_honeypot'));
        add_action('woocommerce_after_checkout_validation', array(__CLASS__, 'validate_checkout'), 20, 2);
        add_action('woocommerce_checkout_create_order', array(__CLASS__, 'attach_to_order'), 30, 2);
        add_action('woocommerce_checkout_order_processed', array(__CLASS__, 'apply_order_action'), 40, 3);
        add_action('woocommerce_store_api_checkout_update_order_from_request', array(__CLASS__, 'protect_store_api'), 30, 2);
        add_action('woocommerce_store_api_checkout_order_processed', array(__CLASS__, 'apply_store_api_action'), 40, 1);
    }

    /**
     * Render an invisible field bots often fill.
     */
    public static function render_honeypot() {
        echo '<p style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">';
        echo '<label for="adoology_website">' . esc_html__('Website', 'adoology-connector') . '</label>';
        echo '<input type="text" id="adoology_website" name="adoology_website" value="" tabindex="-1" autocomplete="off" />';
        echo '</p>';
    }

    /**
     * Block high-risk classic checkout submissions.
     *
     * @param array    $data   Checkout data.
     * @param WP_Error $errors Checkout errors.
     */
    public static function validate_checkout($data, $errors) {
        if (!self::enabled()) {
            return;
        }
        $data['honeypot'] = isset($_POST['adoology_website']) ? sanitize_text_field(wp_unslash($_POST['adoology_website'])) : '';
        self::$assessment = self::evaluate($data, self::cart_product_ids(), true);
        if (self::$assessment['action'] === 'block') {
            $errors->add('adoology_risk_blocked', __('We could not accept this order. Please contact the store for assistance.', 'adoology-connector'));
        }
    }

    /**
     * Save risk result before order creation.
     *
     * @param WC_Order $order Order.
     * @param array    $data  Checkout data.
     */
    public static function attach_to_order($order, $data) {
        if (!$order instanceof WC_Order || !self::enabled()) {
            return;
        }
        $assessment = self::$assessment ?: self::evaluate($data, self::cart_product_ids(), false);
        self::store_order_assessment($order, $assessment);
    }

    /**
     * Hold or flag classic order.
     *
     * @param int      $order_id Order ID.
     * @param array    $posted   Checkout data.
     * @param WC_Order $order    Order.
     */
    public static function apply_order_action($order_id, $posted, $order) {
        if ($order instanceof WC_Order) {
            self::enforce_order_assessment($order);
        }
    }

    /**
     * Assess Store API checkout using server request values.
     *
     * @param WC_Order        $order   Order under construction.
     * @param WP_REST_Request $request Store API request.
     */
    public static function protect_store_api($order, $request) {
        if (!$order instanceof WC_Order || !self::enabled()) {
            return;
        }
        $billing = is_object($request) ? $request->get_param('billing_address') : array();
        $billing = is_array($billing) ? $billing : array();
        $data    = array(
            'billing_phone' => $billing['phone'] ?? '',
            'billing_email' => $billing['email'] ?? '',
            'honeypot'      => '',
        );
        $assessment = self::evaluate($data, self::order_product_ids($order), true);
        if ($assessment['action'] === 'block' && class_exists('Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException')) {
            throw new Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                'adoology_risk_blocked',
                __('We could not accept this order. Please contact the store for assistance.', 'adoology-connector'),
                403
            );
        }
        self::store_order_assessment($order, $assessment);
    }

    /**
     * Hold or flag Store API order.
     *
     * @param WC_Order $order Order.
     */
    public static function apply_store_api_action($order) {
        if ($order instanceof WC_Order) {
            self::enforce_order_assessment($order);
        }
    }

    /**
     * Calculate deterministic risk score and action.
     *
     * @param array $data        Customer/request data.
     * @param array $product_ids Product IDs.
     * @param bool  $count_attempt Record submission velocity.
     * @return array
     */
    public static function evaluate($data, $product_ids = array(), $count_attempt = true) {
        $score   = 0;
        $signals = array();
        $raw_phone = sanitize_text_field((string) ($data['billing_phone'] ?? $data['phone'] ?? ''));
        $phone   = self::normalize_phone($raw_phone);
        $email   = sanitize_email((string) ($data['billing_email'] ?? $data['email'] ?? ''));
        $agent   = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

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
        $limit    = max(2, (int) Adoology_Options::get('adoology_fraud_rate_limit', 5));
        if ($attempts > $limit) {
            $score += min(50, 15 + ($attempts - $limit) * 5);
            $signals[] = 'ip_velocity';
        }

        if (($phone !== '' || $email !== '') && self::has_recent_duplicate($raw_phone, $phone, $email, $product_ids)) {
            $score += 45;
            $signals[] = 'duplicate_order';
        }

        $score = min(100, $score);
        $flag  = max(1, (int) Adoology_Options::get('adoology_fraud_flag_threshold', 30));
        $hold  = max($flag, (int) Adoology_Options::get('adoology_fraud_hold_threshold', 60));
        $block = max($hold, (int) Adoology_Options::get('adoology_fraud_block_threshold', 90));
        $action = $score >= $block ? 'block' : ($score >= $hold ? 'hold' : ($score >= $flag ? 'flag' : 'allow'));

        return array(
            'score'   => $score,
            'action'  => $action,
            'signals' => array_values(array_unique($signals)),
        );
    }

    /**
     * Whether protection is enabled.
     *
     * @return bool
     */
    public static function enabled() {
        return Adoology_Options::get('adoology_fraud_enabled', 'yes') === 'yes';
    }

    /**
     * Store an assessment created by a non-cart order flow.
     *
     * @param WC_Order $order      Order.
     * @param array    $assessment Assessment.
     */
    public static function store_order_assessment($order, $assessment) {
        $order->update_meta_data('_adoology_risk_score', (int) $assessment['score']);
        $order->update_meta_data('_adoology_risk_action', sanitize_key($assessment['action']));
        $order->update_meta_data('_adoology_risk_signals', wp_json_encode($assessment['signals']));
        $order->update_meta_data('_adoology_normalized_phone', self::normalize_phone($order->get_billing_phone()));
    }

    /**
     * Apply stored risk action and emit telemetry.
     *
     * @param WC_Order $order Order.
     */
    public static function enforce_order_assessment($order) {
        if (!self::enabled()) {
            return;
        }
        if ($order->get_meta('_adoology_risk_event_sent', true) === 'yes') {
            return;
        }
        $score   = (int) $order->get_meta('_adoology_risk_score', true);
        $action  = (string) $order->get_meta('_adoology_risk_action', true);
        $signals = json_decode((string) $order->get_meta('_adoology_risk_signals', true), true);
        $signals = is_array($signals) ? $signals : array();
        if ($action === 'hold' && !in_array($order->get_status(), array('cancelled', 'refunded', 'completed'), true)) {
            $order->update_status('on-hold', __('Held by Adoology local risk assessment.', 'adoology-connector'));
        } elseif ($action === 'flag') {
            $order->add_order_note(__('Flagged by Adoology local risk assessment.', 'adoology-connector'));
        }

        $queued = Adoology_Events::enqueue('risk.assessed', Adoology_Incomplete_Orders::anonymous_id(), Adoology_Incomplete_Orders::identity()['session_id'], array(
            'order_id'   => $order->get_id(),
            'risk_score' => $score,
            'action'     => $action ?: 'allow',
            'signals'    => $signals,
        ), Adoology_Incomplete_Orders::request_context());
        if (!is_wp_error($queued)) {
            $order->update_meta_data('_adoology_risk_event_sent', 'yes');
            $order->save_meta_data();
        }
    }

    private static function attempt_count($increment) {
        $ip    = Adoology_Incomplete_Orders::client_ip();
        $key   = 'adoology_attempt_' . hash_hmac('sha256', $ip, wp_salt('nonce'));
        $count = (int) get_transient($key);
        if ($increment) {
            $count++;
            set_transient($key, $count, 10 * MINUTE_IN_SECONDS);
        }
        return $count;
    }

    private static function has_recent_duplicate($raw_phone, $phone, $email, $product_ids) {
        if (!function_exists('wc_get_orders')) {
            return false;
        }
        $minutes = max(5, (int) Adoology_Options::get('adoology_duplicate_window_minutes', 60));
        $base_args = array(
            'limit'        => 10,
            'return'       => 'objects',
            'status'       => array('pending', 'processing', 'on-hold', 'completed'),
            'date_created' => '>' . (time() - $minutes * MINUTE_IN_SECONDS),
        );
        $orders = array();
        foreach (array_values(array_unique(array_filter(array($raw_phone, $phone)))) as $phone_value) {
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
            $args['meta_query'] = array(array('key' => '_adoology_normalized_phone', 'value' => $phone, 'compare' => '='));
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

    private static function cart_product_ids() {
        $ids = array();
        if (function_exists('WC') && WC()->cart) {
            foreach (WC()->cart->get_cart() as $item) {
                $ids[] = (int) $item['product_id'];
            }
        }
        return array_values(array_unique($ids));
    }

    private static function order_product_ids($order) {
        $ids = array();
        foreach ($order->get_items() as $item) {
            $ids[] = (int) $item->get_product_id();
        }
        return array_values(array_unique($ids));
    }

    private static function normalize_phone($phone) {
        return preg_replace('/[^0-9+]/', '', $phone);
    }
}
