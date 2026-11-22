<?php

/**
 * Persistent storefront device ID used by the Adoology blocklist.
 */

namespace Adoology;

use WC_Order;

if (!defined('ABSPATH')) {
    exit;
}

class Device
{
    public const COOKIE = 'adoology_device_id';

    public const META_KEY = '_adoology_device_id';

    /**
     * Register device ID hooks.
     */
    public static function register()
    {
        add_action('template_redirect', [self::class, 'ensure_cookie']);
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_keeper']);
        add_action('woocommerce_checkout_create_order', [self::class, 'attach'], 20, 1);
        add_action('woocommerce_store_api_checkout_update_order_from_request', [self::class, 'attach'], 15, 1);
    }

    /**
     * Give every storefront visitor a device ID. Set regardless of checkout
     * tracking, because blocklist matching depends on it.
     */
    public static function ensure_cookie()
    {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }
        self::id();
    }

    /**
     * Current device ID, created when missing.
     *
     * @return string
     */
    public static function id()
    {
        $value = self::from_cookie();
        if ($value === '') {
            $value = strtolower(wp_generate_uuid4());
            if (!headers_sent()) {
                setcookie(self::COOKIE, $value, [
                    'expires' => time() + 2 * YEAR_IN_SECONDS,
                    'path' => COOKIEPATH ?: '/',
                    'domain' => (string) COOKIE_DOMAIN,
                    'secure' => is_ssl(),
                    'httponly' => false,
                    'samesite' => 'Lax',
                ]);
            }
            $_COOKIE[self::COOKIE] = $value;
        }

        return $value;
    }

    /**
     * Device ID sent by the browser, or an empty string.
     *
     * @return string
     */
    public static function from_cookie()
    {
        $value = isset($_COOKIE[self::COOKIE]) ? strtolower(sanitize_text_field(wp_unslash($_COOKIE[self::COOKIE]))) : '';

        return preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $value) ? $value : '';
    }

    /**
     * Save the device ID on a new order.
     *
     * @param  WC_Order  $order  Order.
     */
    public static function attach($order)
    {
        if (!$order instanceof WC_Order) {
            return;
        }
        $device_id = self::from_cookie();
        if ($device_id !== '') {
            $order->update_meta_data(self::META_KEY, $device_id);
        }
    }

    /**
     * Keep a copy in localStorage so clearing cookies alone does not reset
     * the device ID.
     */
    public static function enqueue_keeper()
    {
        if (is_admin()) {
            return;
        }
        wp_register_script('adoology-device', false, [], ADOOLOGY_VERSION, true);
        wp_enqueue_script('adoology-device');
        $script = sprintf(
            '(function(){try{var n=%1$s,p=/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/,m=document.cookie.match(new RegExp("(?:^|; )"+n+"=([^;]*)")),c=m?decodeURIComponent(m[1]):"",s=localStorage.getItem(n)||"";if(p.test(c)){if(s!==c)localStorage.setItem(n,c)}else if(p.test(s)){document.cookie=n+"="+s+"; path=%2$s; max-age=63072000; SameSite=Lax"+(location.protocol==="https:"?"; Secure":"")}}catch(e){}})();',
            wp_json_encode(self::COOKIE),
            esc_js(COOKIEPATH ?: '/')
        );
        wp_add_inline_script('adoology-device', $script);
    }
}
