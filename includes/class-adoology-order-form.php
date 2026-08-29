<?php
/**
 * Cart-independent landing-page WooCommerce order form.
 *
 * @package Adoology_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

class Adoology_Order_Form {

    /**
     * Register shortcode, block, and submission handlers.
     */
    public static function register() {
        add_shortcode('adoology_order_form', array(__CLASS__, 'shortcode'));
        add_action('admin_post_adoology_submit_order', array(__CLASS__, 'submit'));
        add_action('admin_post_nopriv_adoology_submit_order', array(__CLASS__, 'submit'));
        add_action('init', array(__CLASS__, 'register_block'));
        add_action('enqueue_block_editor_assets', array(__CLASS__, 'editor_assets'));
    }

    /**
     * Register dynamic block.
     */
    public static function register_block() {
        if (function_exists('register_block_type')) {
            register_block_type('adoology/order-form', array(
                'api_version'     => 2,
                'attributes'      => array(
                    'productId' => array('type' => 'integer', 'default' => 0),
                    'title'     => array('type' => 'string', 'default' => ''),
                ),
                'render_callback' => array(__CLASS__, 'render_block'),
            ));
        }
    }

    /**
     * Register block editor UI.
     */
    public static function editor_assets() {
        wp_enqueue_script(
            'adoology-order-form-block',
            plugins_url('assets/js/order-form-block.js', ADOOLOGY_PLUGIN_FILE),
            array('wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n'),
            ADOOLOGY_VERSION,
            true
        );
    }

    /**
     * Render dynamic block.
     *
     * @param array $attributes Block attributes.
     * @return string
     */
    public static function render_block($attributes) {
        return self::shortcode(array(
            'product_id' => isset($attributes['productId']) ? (int) $attributes['productId'] : 0,
            'title'      => isset($attributes['title']) ? $attributes['title'] : '',
        ));
    }

    /**
     * Render order form shortcode.
     *
     * @param array $attributes Shortcode attributes.
     * @return string
     */
    public static function shortcode($attributes) {
        if (Adoology_Options::get('adoology_order_form_enabled', 'yes') !== 'yes') {
            return '';
        }
        $attributes = shortcode_atts(array(
            'product_id'      => 0,
            'title'           => __('Order now', 'adoology-connector'),
            'delivery_options' => 'standard:' . __('Standard delivery', 'adoology-connector') . ',pickup:' . __('Pickup', 'adoology-connector'),
        ), $attributes, 'adoology_order_form');
        $product = wc_get_product((int) $attributes['product_id']);
        if (!$product || !$product->is_purchasable()) {
            return current_user_can('edit_posts') ? '<p>' . esc_html__('Select a purchasable WooCommerce product for this Adoology order form.', 'adoology-connector') . '</p>' : '';
        }

        Adoology_Incomplete_Orders::enqueue_tracker('order_form', array(
            'currency'    => get_woocommerce_currency(),
            'value_minor' => Adoology_Incomplete_Orders::to_minor($product->get_price()),
            'items'       => array(array('product_id' => $product->get_id(), 'quantity' => 1)),
        ));
        $gateways = self::enabled_gateways();
        $delivery = self::delivery_options($attributes['delivery_options']);
        $error_token = isset($_GET['adoology_form_error']) ? sanitize_key(wp_unslash($_GET['adoology_form_error'])) : '';
        $error       = preg_match('/^[a-f0-9]{32}$/', $error_token) ? get_transient('adoology_form_error_' . $error_token) : '';
        if ($error_token !== '') {
            delete_transient('adoology_form_error_' . $error_token);
        }
        $delivery_json = wp_json_encode($delivery);
        $delivery_data = base64_encode((string) $delivery_json);
        $delivery_signature = hash_hmac('sha256', $delivery_data, wp_salt('nonce'));

        ob_start();
        self::styles();
        ?>
        <form class="adoology-order-form" data-adoology-order-form="1" data-product-id="<?php echo esc_attr((string) $product->get_id()); ?>" data-value-minor="<?php echo esc_attr((string) Adoology_Incomplete_Orders::to_minor($product->get_price())); ?>" data-currency="<?php echo esc_attr(get_woocommerce_currency()); ?>" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="adoology_submit_order" />
            <input type="hidden" name="product_id" value="<?php echo esc_attr((string) $product->get_id()); ?>" />
            <input type="hidden" name="_adoology_checkout_id" value="" />
            <input type="hidden" name="delivery_config" value="<?php echo esc_attr($delivery_data); ?>" />
            <input type="hidden" name="delivery_signature" value="<?php echo esc_attr($delivery_signature); ?>" />
            <?php wp_nonce_field('adoology_submit_order', '_adoology_nonce'); ?>
            <div class="adoology-form-head">
                <div>
                    <span class="adoology-form-kicker"><?php esc_html_e('Secure WooCommerce order', 'adoology-connector'); ?></span>
                    <h3><?php echo esc_html($attributes['title']); ?></h3>
                </div>
                <strong><?php echo wp_kses_post($product->get_price_html()); ?></strong>
            </div>
            <?php if (is_string($error) && $error !== '') : ?>
                <div class="adoology-form-error" role="alert"><?php echo esc_html($error); ?></div>
            <?php endif; ?>
            <div class="adoology-form-grid">
                <p><label><?php esc_html_e('Name', 'adoology-connector'); ?><input name="adoology_name" type="text" maxlength="190" autocomplete="name" required /></label></p>
                <p><label><?php esc_html_e('Phone', 'adoology-connector'); ?><input name="adoology_phone" type="tel" maxlength="40" autocomplete="tel" required /></label></p>
                <p><label><?php esc_html_e('Email', 'adoology-connector'); ?><input name="adoology_email" type="email" maxlength="190" autocomplete="email" /></label></p>
                <p><label><?php esc_html_e('Quantity', 'adoology-connector'); ?><input name="quantity" type="number" value="1" min="1" max="99" required /></label></p>
                <?php self::variation_field($product); ?>
                <p class="adoology-wide"><label><?php esc_html_e('Address', 'adoology-connector'); ?><input name="adoology_address" type="text" maxlength="500" autocomplete="street-address" required /></label></p>
                <p><label><?php esc_html_e('City', 'adoology-connector'); ?><input name="adoology_city" type="text" maxlength="190" autocomplete="address-level2" required /></label></p>
                <p><label><?php esc_html_e('Postcode', 'adoology-connector'); ?><input name="adoology_postcode" type="text" maxlength="30" autocomplete="postal-code" /></label></p>
                <p><label><?php esc_html_e('Delivery', 'adoology-connector'); ?><select name="delivery_option" required><?php foreach ($delivery as $value => $option) : ?><option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($option['label'] . ($option['cost'] > 0 ? ' - ' . wp_strip_all_tags(wc_price($option['cost'])) : '')); ?></option><?php endforeach; ?></select></label></p>
                <p><label><?php esc_html_e('Preferred payment', 'adoology-connector'); ?><select name="payment_method" required><?php foreach ($gateways as $gateway) : ?><option value="<?php echo esc_attr($gateway->id); ?>"><?php echo esc_html($gateway->get_title()); ?></option><?php endforeach; ?></select></label></p>
            </div>
            <p class="adoology-honeypot" aria-hidden="true"><label>Website<input name="adoology_website" type="text" tabindex="-1" autocomplete="off" /></label></p>
            <button type="submit" class="adoology-submit"><?php esc_html_e('Place order', 'adoology-connector'); ?></button>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Validate submission and create normal WooCommerce order.
     */
    public static function submit() {
        $referer = wp_get_referer() ?: home_url('/');
        $nonce   = sanitize_text_field(wp_unslash($_POST['_adoology_nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'adoology_submit_order') || Adoology_Options::get('adoology_order_form_enabled', 'yes') !== 'yes') {
            self::fail(__('Order form security check failed.', 'adoology-connector'), $referer);
        }

        $product_id  = absint($_POST['product_id'] ?? 0);
        $variation_id = absint($_POST['variation_id'] ?? 0);
        $quantity    = max(1, min(99, absint($_POST['quantity'] ?? 1)));
        $product     = $variation_id ? wc_get_product($variation_id) : wc_get_product($product_id);
        $variation   = $variation_id && $product ? $product->get_variation_attributes() : array();
        $valid_add   = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation);
        if (!$product || !$product->is_purchasable() || ($variation_id && (int) $product->get_parent_id() !== $product_id) || !$product->has_enough_stock($quantity) || ($product->is_sold_individually() && $quantity > 1) || !$valid_add) {
            self::fail(__('Selected product is unavailable.', 'adoology-connector'), $referer);
        }

        $name    = sanitize_text_field(wp_unslash($_POST['adoology_name'] ?? ''));
        $phone   = sanitize_text_field(wp_unslash($_POST['adoology_phone'] ?? ''));
        $email   = sanitize_email(wp_unslash($_POST['adoology_email'] ?? ''));
        $address = sanitize_text_field(wp_unslash($_POST['adoology_address'] ?? ''));
        $city    = sanitize_text_field(wp_unslash($_POST['adoology_city'] ?? ''));
        $postcode = sanitize_text_field(wp_unslash($_POST['adoology_postcode'] ?? ''));
        $country = sanitize_key(WC()->countries->get_base_country());
        if ($name === '' || $phone === '' || $address === '' || $city === '') {
            self::fail(__('Name, phone, address, and city are required.', 'adoology-connector'), $referer);
        }

        $risk = Adoology_Fraud::enabled() ? Adoology_Fraud::evaluate(array(
            'phone'    => $phone,
            'email'    => $email,
            'honeypot' => sanitize_text_field(wp_unslash($_POST['adoology_website'] ?? '')),
        ), array($product_id), true) : array('score' => 0, 'action' => 'allow', 'signals' => array());
        if ($risk['action'] === 'block') {
            Adoology_Events::enqueue('order.blocked', Adoology_Incomplete_Orders::anonymous_id(), Adoology_Incomplete_Orders::identity()['session_id'], array(
                'product_id' => $product_id,
                'risk_score' => $risk['score'],
                'signals'    => $risk['signals'],
            ), Adoology_Incomplete_Orders::request_context());
            self::fail(__('We could not accept this order. Please contact the store for assistance.', 'adoology-connector'), $referer);
        }

        $checkout_id = sanitize_text_field(wp_unslash($_POST['_adoology_checkout_id'] ?? ''));
        if (!preg_match('/^[a-f0-9-]{36}$/Di', $checkout_id)) {
            $checkout_id = self::fallback_checkout_id($nonce, $phone, $product_id);
        }
        $identity    = Adoology_Incomplete_Orders::identity();
        $snapshot = Adoology_Incomplete_Orders::store_snapshot($checkout_id, array(
            'anonymous_id' => $identity['anonymous_id'],
            'session_id'   => $identity['session_id'],
            'flow'         => 'order_form',
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'quantity'     => $quantity,
            'value_minor'  => Adoology_Incomplete_Orders::to_minor((float) $product->get_price() * $quantity),
            'currency'     => get_woocommerce_currency(),
            'landing_page' => $referer,
            'form_stage'   => 'submitted',
            'customer'     => compact('name', 'phone', 'email', 'address', 'city', 'postcode', 'country'),
        ));
        if (is_wp_error($snapshot)) {
            self::fail(__('Could not prepare this order. Please try again.', 'adoology-connector'), $referer);
        }
        $claim = Adoology_Incomplete_Orders::claim_submission($checkout_id);
        if (is_wp_error($claim)) {
            self::fail($claim->get_error_message(), $referer);
        }
        if (!$claim['claimed'] && $claim['order_id']) {
            $existing_order = wc_get_order($claim['order_id']);
            if ($existing_order) {
                wp_safe_redirect($existing_order->needs_payment() ? $existing_order->get_checkout_payment_url() : $existing_order->get_checkout_order_received_url());
                exit;
            }
        }

        $delivery_data      = sanitize_text_field(wp_unslash($_POST['delivery_config'] ?? ''));
        $delivery_signature = sanitize_text_field(wp_unslash($_POST['delivery_signature'] ?? ''));
        $delivery_options   = json_decode((string) base64_decode($delivery_data, true), true);
        $delivery_option    = sanitize_key(wp_unslash($_POST['delivery_option'] ?? ''));
        if (!hash_equals(hash_hmac('sha256', $delivery_data, wp_salt('nonce')), $delivery_signature) || !is_array($delivery_options) || !isset($delivery_options[$delivery_option])) {
            Adoology_Incomplete_Orders::release_submission($checkout_id);
            self::fail(__('Selected delivery option is unavailable.', 'adoology-connector'), $referer);
        }

        $order = null;
        try {
            $order = wc_create_order(array('customer_id' => get_current_user_id()));
            $order->set_created_via('adoology-order-form');
            $order->add_product($product, $quantity);
            list($first_name, $last_name) = self::split_name($name);
            foreach (array('billing', 'shipping') as $type) {
                $order->{'set_' . $type . '_first_name'}($first_name);
                $order->{'set_' . $type . '_last_name'}($last_name);
                $order->{'set_' . $type . '_address_1'}($address);
                $order->{'set_' . $type . '_city'}($city);
                $order->{'set_' . $type . '_postcode'}($postcode);
                $order->{'set_' . $type . '_country'}(strtoupper($country));
            }
            $order->set_billing_phone($phone);
            $order->set_billing_email($email);

            $payment_method = sanitize_key(wp_unslash($_POST['payment_method'] ?? ''));
            $gateways       = self::enabled_gateways();
            if (!isset($gateways[$payment_method])) {
                throw new Exception(__('Selected payment method is unavailable.', 'adoology-connector'));
            }
            $order->update_meta_data('_adoology_preferred_payment_method', $payment_method);
            $shipping = new WC_Order_Item_Shipping();
            $shipping->set_method_title($delivery_options[$delivery_option]['label']);
            $shipping->set_method_id('adoology_' . $delivery_option);
            $shipping->set_total((float) $delivery_options[$delivery_option]['cost']);
            $order->add_item($shipping);
            $order->update_meta_data('_adoology_delivery_option', $delivery_option);
            $order->update_meta_data('_adoology_checkout_id', $checkout_id);
            if (Adoology_Fraud::enabled()) {
                Adoology_Fraud::store_order_assessment($order, $risk);
            }
            $order->calculate_totals();
            $order->save();
            do_action('woocommerce_checkout_order_created', $order);
            if (function_exists('wc_reserve_stock_for_order') && $order->needs_payment()) {
                wc_reserve_stock_for_order($order);
            }
            if (Adoology_Fraud::enabled()) {
                Adoology_Fraud::enforce_order_assessment($order);
            }

            if ($risk['action'] === 'hold') {
                $redirect = $order->get_checkout_order_received_url();
            } elseif (!$order->needs_payment()) {
                $order->payment_complete();
                $redirect = $order->get_checkout_order_received_url();
            } else {
                $redirect = $order->get_checkout_payment_url();
            }
            Adoology_Incomplete_Orders::mark_complete($checkout_id, $order->get_id(), $order);
            wp_safe_redirect($redirect);
            exit;
        } catch (Throwable $throwable) {
            if ($order instanceof WC_Order && !$order->is_paid()) {
                $order->delete(true);
            }
            Adoology_Incomplete_Orders::release_submission($checkout_id);
            Adoology_Logger::log('error', 'Landing-page order creation failed.', array('error' => $throwable->getMessage()));
            self::fail(__('Could not create the order. Please review your details and try again.', 'adoology-connector'), $referer);
        }
    }

    private static function variation_field($product) {
        if (!$product->is_type('variable')) {
            return;
        }
        echo '<p class="adoology-wide"><label>' . esc_html__('Variation', 'adoology-connector') . '<select name="variation_id" required>';
        echo '<option value="">' . esc_html__('Choose an option', 'adoology-connector') . '</option>';
        foreach ($product->get_children() as $variation_id) {
            $variation = wc_get_product($variation_id);
            if ($variation && $variation->is_purchasable() && $variation->is_in_stock()) {
                echo '<option value="' . esc_attr((string) $variation_id) . '">' . esc_html(wc_get_formatted_variation($variation, true, false, true)) . '</option>';
            }
        }
        echo '</select></label></p>';
    }

    private static function delivery_options($raw) {
        $options = array();
        foreach (explode(',', (string) $raw) as $entry) {
            $parts = array_map('trim', explode(':', $entry, 3));
            if (count($parts) >= 2 && sanitize_key($parts[0]) !== '') {
                $options[sanitize_key($parts[0])] = array(
                    'label' => sanitize_text_field($parts[1]),
                    'cost'  => isset($parts[2]) ? max(0, (float) wc_format_decimal($parts[2])) : 0,
                );
            }
        }
        return $options ?: array('standard' => array('label' => __('Standard delivery', 'adoology-connector'), 'cost' => 0));
    }

    private static function split_name($name) {
        $parts = preg_split('/\s+/', trim($name), 2);
        return array($parts[0] ?? '', $parts[1] ?? '');
    }

    private static function fail($message, $redirect) {
        try {
            $token = bin2hex(random_bytes(16));
        } catch (Exception $exception) {
            $token = md5(wp_generate_uuid4());
        }
        set_transient('adoology_form_error_' . $token, substr(sanitize_text_field($message), 0, 500), 5 * MINUTE_IN_SECONDS);
        wp_safe_redirect(add_query_arg('adoology_form_error', $token, wp_validate_redirect($redirect, home_url('/'))));
        exit;
    }

    private static function enabled_gateways() {
        $enabled = array();
        if (!function_exists('WC') || !WC()->payment_gateways()) {
            return $enabled;
        }
        foreach (WC()->payment_gateways()->payment_gateways() as $gateway) {
            if ($gateway->enabled === 'yes') {
                $enabled[$gateway->id] = $gateway;
            }
        }
        return $enabled;
    }

    private static function fallback_checkout_id($nonce, $phone, $product_id) {
        $bucket = (int) floor(time() / (10 * MINUTE_IN_SECONDS));
        $hash   = hash_hmac('sha256', $nonce . '|' . $phone . '|' . (int) $product_id . '|' . Adoology_Incomplete_Orders::client_ip() . '|' . $bucket, wp_salt('nonce'));
        return substr($hash, 0, 8) . '-' . substr($hash, 8, 4) . '-4' . substr($hash, 13, 3) . '-a' . substr($hash, 17, 3) . '-' . substr($hash, 20, 12);
    }

    private static function styles() {
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        ?>
        <style>
            .adoology-order-form{--ado-ink:#14231d;--ado-accent:#e85d35;max-width:680px;padding:clamp(22px,4vw,38px);border:1px solid #dce4df;border-radius:22px;background:#f7faf8;box-shadow:0 22px 60px rgba(20,35,29,.10);color:var(--ado-ink)}
            .adoology-form-head{display:flex;justify-content:space-between;gap:20px;align-items:flex-end;margin-bottom:24px}.adoology-form-head h3{margin:3px 0 0;font-size:clamp(25px,4vw,38px);line-height:1}.adoology-form-kicker{font-size:12px;letter-spacing:.11em;text-transform:uppercase;color:#557066}.adoology-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.adoology-form-grid p{margin:0}.adoology-form-grid label{display:grid;gap:6px;font-size:13px;font-weight:650}.adoology-form-grid input,.adoology-form-grid select{width:100%;min-height:46px;padding:10px 12px;border:1px solid #cbd8d1;border-radius:10px;background:#fff;color:var(--ado-ink)}.adoology-wide{grid-column:1/-1}.adoology-submit{width:100%;margin-top:20px;padding:15px 20px;border:0;border-radius:12px;background:var(--ado-accent);color:#fff;font-weight:750;cursor:pointer}.adoology-submit:hover{filter:brightness(.94)}.adoology-form-error{margin-bottom:18px;padding:12px;border-left:4px solid #b42318;background:#fff1f0;color:#7a271a}.adoology-honeypot{position:absolute!important;left:-10000px!important;width:1px!important;height:1px!important;overflow:hidden!important}@media(max-width:580px){.adoology-form-grid{grid-template-columns:1fr}.adoology-wide{grid-column:auto}.adoology-form-head{align-items:flex-start;flex-direction:column}}
        </style>
        <?php
    }
}
