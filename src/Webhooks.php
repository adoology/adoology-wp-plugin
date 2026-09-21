<?php

/**
 * Native WooCommerce webhook payloads, loop suppression, and retries.
 */

namespace Adoology;

use WC_DateTime;
use WC_Product;
use WC_Product_Attribute;
use WC_Product_Download;
use WC_Product_Variation;
use WC_Webhook;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Webhooks
{
    const RETRY_HOOK = 'adoology_retry_webhook_delivery';

    const RETRY_LIMIT = 3;

    const RETRY_MAX_STATES = 200;

    const RETRY_MAX_BODY = 5242880;

    /** @var array */
    private static $reveal_secret_once = [];

    /** @var array */
    private static $deletion_types = [];

    /** @var array */
    private static $variation_parents = [];

    /** @var array */
    private static $routed_post_actions = [];

    /** @var bool|null */
    private static $valid_sync_request = null;

    /**
     * Register filters before WooCommerce loads active native webhooks.
     */
    public static function register()
    {
        add_filter('woocommerce_webhook_topic_hooks', [self::class, 'filter_topic_hooks'], 20, 2);
        add_filter('woocommerce_webhook_should_deliver', [self::class, 'filter_should_deliver'], 20, 3);
        add_filter('woocommerce_webhook_payload', [self::class, 'filter_payload'], 20, 4);
        add_filter('woocommerce_webhook_http_args', [self::class, 'filter_http_args'], 20, 3);
        add_filter('woocommerce_webhook_secret', [self::class, 'filter_webhook_secret'], 20, 2);
        add_action('woocommerce_webhook_delivery', [self::class, 'handle_delivery'], 20, 5);
        add_action(self::RETRY_HOOK, [self::class, 'retry_delivery'], 10, 1);

        add_action('before_delete_post', [self::class, 'capture_before_delete'], 1, 1);
        add_action('wp_trash_post', [self::class, 'capture_before_trash'], 1, 1);
        add_action('woocommerce_before_delete_product_variation', [self::class, 'capture_variation_parent'], 1, 1);
    }

    /**
     * Correct variation routing and cover permanent parent deletion.
     *
     * @param  array  $hooks  Topic hook map.
     * @param  WC_Webhook  $webhook  Webhook context.
     * @return array
     */
    public static function filter_topic_hooks($hooks, $webhook)
    {
        if (!$webhook instanceof WC_Webhook || !Connection::is_managed_webhook_id($webhook->get_id())) {
            return $hooks;
        }

        if (isset($hooks['product.created'])) {
            $hooks['product.created'] = array_values(array_diff($hooks['product.created'], ['woocommerce_new_product_variation']));
        }

        if (isset($hooks['product.updated'])) {
            $hooks['product.updated'] = array_values(array_unique(array_merge($hooks['product.updated'], [
                'woocommerce_new_product_variation',
                'woocommerce_delete_product_variation',
                'woocommerce_trash_product_variation',
                'wp_trash_post',
                'delete_post',
                'untrashed_post',
            ])));
        }

        if (isset($hooks['product.deleted'])) {
            $hooks['product.deleted'] = array_values(array_unique(array_merge($hooks['product.deleted'], [
                'before_delete_post',
                'delete_post',
            ])));
        }

        return $hooks;
    }

    /**
     * Suppress valid backend echoes and route variation lifecycle events.
     *
     * @param  bool  $should  WooCommerce decision.
     * @param  WC_Webhook  $webhook  Webhook.
     * @param  mixed  $arg  Resource argument.
     * @return bool
     */
    public static function filter_should_deliver($should, $webhook, $arg)
    {
        if (!$webhook instanceof WC_Webhook || !Connection::is_managed_webhook_id($webhook->get_id())) {
            return $should;
        }

        if (self::is_valid_backend_sync_request()) {
            return false;
        }

        $topic = (string) $webhook->get_topic();
        $id = self::resource_id($arg);
        $type = self::resource_type($id, $arg);
        $hook = current_action();

        if ($type === 'product_variation') {
            if ($topic !== 'product.updated') {
                return false;
            }

            $parent_id = self::variation_parent_id($id, $arg);
            if ($parent_id <= 0 || get_transient(self::parent_deleting_key($parent_id))) {
                return false;
            }

            $post_actions = ['wp_trash_post', 'delete_post', 'untrashed_post'];
            if (in_array($hook, $post_actions, true)) {
                $route_key = $webhook->get_id() . '|' . $hook . '|' . $id;
                if (isset(self::$routed_post_actions[$route_key])) {
                    return false;
                }
                self::$routed_post_actions[$route_key] = true;

                return $webhook->get_status() === 'active';
            }

            return $should;
        }

        if ($type === 'product' && $topic === 'product.updated' && in_array($hook, ['wp_trash_post', 'delete_post', 'before_delete_post'], true)) {
            return false;
        }

        if ($type !== 'product' && in_array($topic, Connection::WEBHOOK_TOPICS, true)) {
            return false;
        }

        return $should;
    }

    /**
     * Replace every managed product payload with an explicit parent snapshot.
     *
     * @param  mixed  $payload  Original payload.
     * @param  string  $resource  Resource type.
     * @param  mixed  $resource_id  Resource ID or object.
     * @param  int  $webhook_id  Webhook ID.
     * @return array
     */
    public static function filter_payload($payload, $resource, $resource_id, $webhook_id)
    {
        if ($resource !== 'product' || !Connection::is_managed_webhook_id($webhook_id)) {
            return $payload;
        }

        $webhook = function_exists('wc_get_webhook') ? wc_get_webhook((int) $webhook_id) : null;
        $topic = $webhook ? (string) $webhook->get_topic() : '';
        $id = self::resource_id($resource_id);

        if ($topic === 'product.deleted') {
            $snapshot = get_transient(self::deleted_snapshot_key($id));
            if (is_array($snapshot)) {
                return $snapshot;
            }

            $deleted_at = gmdate('Y-m-d\TH:i:s\Z');

            return [
                'snapshot_version' => 1,
                'id' => $id,
                'type' => 'product',
                'deleted_at_gmt' => $deleted_at,
                'date_modified' => $deleted_at,
                'date_modified_gmt' => $deleted_at,
                'currency' => get_woocommerce_currency(),
                'meta_data' => [],
                'variations' => [],
            ];
        }

        $parent_id = self::parent_product_id($id, $resource_id);
        $product = $parent_id > 0 ? wc_get_product($parent_id) : null;
        if (!$product instanceof WC_Product || $product->is_type('variation')) {
            return [
                'snapshot_version' => 1,
                'id' => $parent_id > 0 ? $parent_id : $id,
                'type' => 'product',
                'date_modified' => null,
                'date_modified_gmt' => null,
                'currency' => get_woocommerce_currency(),
                'meta_data' => [],
                'variations' => [],
            ];
        }

        return self::product_snapshot($product);
    }

    /**
     * Add stable event ID and harden native delivery transport.
     *
     * @param  array  $http_args  HTTP arguments.
     * @param  mixed  $arg  Resource argument.
     * @param  int  $webhook_id  Webhook ID.
     * @return array
     */
    public static function filter_http_args($http_args, $arg, $webhook_id)
    {
        if (!Connection::is_managed_webhook_id($webhook_id)) {
            return $http_args;
        }

        $webhook = wc_get_webhook((int) $webhook_id);
        if (!$webhook) {
            return $http_args;
        }

        $body = isset($http_args['body']) ? (string) $http_args['body'] : '';
        if (!isset($http_args['headers']) || !is_array($http_args['headers'])) {
            $http_args['headers'] = [];
        }
        $http_args['headers']['X-Adoology-Event-ID'] = self::event_id($webhook->get_topic(), $body);
        $http_args['timeout'] = 20;
        $http_args['redirection'] = 0;
        $http_args['reject_unsafe_urls'] = true;
        $http_args['httpversion'] = '1.1';

        // WC calls get_secret() immediately after this filter to sign the body.
        self::$reveal_secret_once[(int) $webhook_id] = 1;

        return $http_args;
    }

    /**
     * Reveal decrypted secret only for one native signature operation.
     * Admin screens and other callers receive a non-secret marker.
     *
     * @param  string  $stored  Stored Woo webhook value.
     * @param  int  $webhook_id  Webhook ID.
     * @return string
     */
    public static function filter_webhook_secret($stored, $webhook_id)
    {
        if (!Connection::is_managed_webhook_id($webhook_id)) {
            return $stored;
        }

        if (!empty(self::$reveal_secret_once[(int) $webhook_id])) {
            unset(self::$reveal_secret_once[(int) $webhook_id]);
            $secret = Crypto::get_secret('adoology_webhook_secret');
            if (!is_wp_error($secret) && $secret !== '') {
                return $secret;
            }
            Logger::log('error', 'Managed webhook signing secret could not be read.', ['webhook_id' => (int) $webhook_id]);

            return '';
        }

        return Connection::WEBHOOK_SECRET_MARKER;
    }

    /**
     * Observe native delivery result and enqueue bounded exact-body retries.
     *
     * @param  array  $http_args  HTTP arguments.
     * @param  array|WP_Error  $response  HTTP response.
     * @param  float  $duration  Duration.
     * @param  mixed  $arg  Resource argument.
     * @param  int  $webhook_id  Webhook ID.
     */
    public static function handle_delivery($http_args, $response, $duration, $arg, $webhook_id)
    {
        if (!Connection::is_managed_webhook_id($webhook_id)) {
            return;
        }

        $headers = isset($http_args['headers']) && is_array($http_args['headers']) ? $http_args['headers'] : [];
        $event_id = isset($headers['X-Adoology-Event-ID']) ? (string) $headers['X-Adoology-Event-ID'] : '';
        $status = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);

        if ($status >= 200 && $status < 300) {
            if ($event_id !== '') {
                self::clear_retry($event_id);
            }
            $webhook = wc_get_webhook((int) $webhook_id);
            if ($webhook && $webhook->get_topic() === 'product.deleted') {
                delete_transient(self::deleted_snapshot_key(self::resource_id($arg)));
            }

            return;
        }

        self::queue_retry(
            $event_id,
            (int) $webhook_id,
            isset($http_args['body']) ? (string) $http_args['body'] : ''
        );
    }

    /**
     * Process one stored retry body.
     *
     * @param  string  $event_id  Stable event ID.
     */
    public static function retry_delivery($event_id)
    {
        if (!is_string($event_id) || !preg_match('/^[a-f0-9]{64}$/D', $event_id)) {
            return;
        }

        $state = get_transient(self::retry_transient_key($event_id));
        if (!is_array($state) || empty($state['body']) || empty($state['webhook_id'])) {
            self::clear_retry($event_id);

            return;
        }

        $webhook_id = (int) $state['webhook_id'];
        $webhook = Connection::is_managed_webhook_id($webhook_id) ? wc_get_webhook($webhook_id) : null;
        $secret = Crypto::get_secret('adoology_webhook_secret');
        if (!$webhook || is_wp_error($secret) || $secret === '' || $webhook->get_delivery_url() !== Connection::receiver_url()) {
            self::clear_retry($event_id);

            return;
        }

        $body = (string) $state['body'];
        $topic = (string) $webhook->get_topic();
        $topic_bits = explode('.', $topic, 2);
        $response = wp_safe_remote_request($webhook->get_delivery_url(), [
            'method' => 'POST',
            'timeout' => 20,
            'redirection' => 0,
            'reject_unsafe_urls' => true,
            'httpversion' => '1.1',
            'blocking' => true,
            'user-agent' => sprintf('WooCommerce/%s Hookshot (Adoology retry)', defined('WC_VERSION') ? WC_VERSION : ''),
            'body' => $body,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-WC-Webhook-Source' => home_url('/'),
                'X-WC-Webhook-Topic' => $topic,
                'X-WC-Webhook-Resource' => $topic_bits[0] ?? 'product',
                'X-WC-Webhook-Event' => $topic_bits[1] ?? 'updated',
                'X-WC-Webhook-Signature' => base64_encode(hash_hmac('sha256', $body, wp_specialchars_decode($secret, ENT_QUOTES), true)),
                'X-WC-Webhook-ID' => $webhook_id,
                'X-WC-Webhook-Delivery-ID' => $webhook->get_new_delivery_id(),
                'X-Adoology-Event-ID' => $event_id,
            ],
        ]);

        $status = is_wp_error($response) ? 0 : (int) wp_remote_retrieve_response_code($response);
        $attempt = isset($state['attempt']) ? (int) $state['attempt'] + 1 : 1;
        if ($status >= 200 && $status < 300) {
            self::clear_retry($event_id);
            $webhook->set_failure_count(0);
            if ($webhook->get_status() !== 'active') {
                $webhook->set_status('active');
            }
            $webhook->save();
            Logger::log('info', 'Adoology webhook retry succeeded.', ['webhook_id' => $webhook_id, 'attempt' => $attempt]);

            return;
        }

        if ($attempt >= self::RETRY_LIMIT) {
            self::clear_retry($event_id);
            Options::update('adoology_last_error', [
                'message' => __('A product webhook exhausted its delivery retries.', 'adoology-connector'),
                'time' => gmdate('Y-m-d H:i:s'),
            ]);
            Logger::log('error', 'Adoology webhook retries exhausted.', ['webhook_id' => $webhook_id, 'status' => $status]);

            return;
        }

        $state['attempt'] = $attempt;
        set_transient(self::retry_transient_key($event_id), $state, 2 * DAY_IN_SECONDS);
        self::schedule_retry($event_id, $attempt + 1);
        Logger::log('warning', 'Adoology webhook retry failed.', ['webhook_id' => $webhook_id, 'attempt' => $attempt, 'status' => $status]);
    }

    /**
     * Capture a product before permanent deletion.
     *
     * @param  int  $post_id  Post ID.
     */
    public static function capture_before_delete($post_id)
    {
        self::capture_deletion_context((int) $post_id);
    }

    /**
     * Capture a product before trashing and cascading variation trash.
     *
     * @param  int  $post_id  Post ID.
     */
    public static function capture_before_trash($post_id)
    {
        self::capture_deletion_context((int) $post_id);
    }

    /**
     * Capture parent before Woo removes a variation.
     *
     * @param  int  $variation_id  Variation ID.
     */
    public static function capture_variation_parent($variation_id)
    {
        $post = get_post((int) $variation_id);
        if ($post && $post->post_type === 'product_variation') {
            self::remember_variation((int) $variation_id, (int) $post->post_parent);
        }
    }

    /**
     * Stable event identifier derived from topic and exact allowlisted body.
     *
     * @param  string  $topic  Topic.
     * @param  string  $body  JSON body.
     * @return string
     */
    public static function event_id($topic, $body)
    {
        return hash('sha256', (string) $topic . "\n" . (string) $body);
    }

    /**
     * Pure signature/freshness validator for backend Woo REST writes.
     *
     * @param  string  $source  Source header.
     * @param  string  $event  Event header.
     * @param  string  $timestamp  Timestamp header.
     * @param  string  $signature  Base64 HMAC header.
     * @param  string  $secret  Shared secret.
     * @param  int|null  $now  Current Unix time for tests.
     * @return bool
     */
    public static function is_valid_sync_signature($source, $event, $timestamp, $signature, $secret, $now = null)
    {
        if (strtolower(trim((string) $source)) !== 'adoology' || $secret === '') {
            return false;
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/D', (string) $event) || strlen((string) $timestamp) > 64 || strlen((string) $signature) > 256) {
            return false;
        }

        if (preg_match('/^[0-9]{9,16}$/D', (string) $timestamp)) {
            $event_time = (float) $timestamp;
            if ($event_time > 20000000000) {
                $event_time = $event_time / 1000;
            }
            $event_time = (int) $event_time;
        } else {
            $event_time = strtotime((string) $timestamp);
        }

        $now = $now === null ? time() : (int) $now;
        if (!$event_time || abs($now - $event_time) > 300) {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', (string) $event . '.' . (string) $timestamp, (string) $secret, true));

        return hash_equals($expected, (string) $signature);
    }

    /**
     * Remove all persisted retry payloads and pending retry actions.
     */
    public static function clear_all_retries()
    {
        $index = Options::get('adoology_webhook_retry_index', []);
        if (is_array($index)) {
            foreach (array_keys($index) as $event_id) {
                delete_transient(self::retry_transient_key($event_id));
            }
        }
        Options::delete('adoology_webhook_retry_index');
        Scheduler::unschedule_hook(self::RETRY_HOOK);
    }

    /**
     * Validate current HTTP request headers once.
     *
     * @return bool
     */
    private static function is_valid_backend_sync_request()
    {
        if (self::$valid_sync_request !== null) {
            return self::$valid_sync_request;
        }

        $secret = Crypto::get_secret('adoology_webhook_secret');
        if (is_wp_error($secret) || $secret === '') {
            self::$valid_sync_request = false;

            return false;
        }

        self::$valid_sync_request = self::is_valid_sync_signature(
            self::request_header('X-Adoology-Sync-Source'),
            self::request_header('X-Adoology-Sync-Event'),
            self::request_header('X-Adoology-Sync-Timestamp'),
            self::request_header('X-Adoology-Sync-Signature'),
            $secret
        );

        return self::$valid_sync_request;
    }

    /**
     * Read one request header from the server environment.
     *
     * @param  string  $name  Header name.
     * @return string
     */
    private static function request_header($name)
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

        // Header values are opaque ASCII tokens; sanitize for storage while preserving separators.
        return isset($_SERVER[$key]) && is_scalar($_SERVER[$key]) ? sanitize_text_field(trim((string) wp_unslash($_SERVER[$key]))) : '';
    }

    /**
     * Build explicit parent product snapshot.
     *
     * @param  WC_Product  $product  Parent product.
     * @return array
     */
    private static function product_snapshot($product)
    {
        $weight = (string) $product->get_weight('edit');
        $data = [
            'snapshot_version' => 1,
            'id' => (int) $product->get_id(),
            'parent_id' => 0,
            'name' => (string) $product->get_name('edit'),
            'slug' => (string) $product->get_slug('edit'),
            'permalink' => (string) $product->get_permalink(),
            'type' => (string) $product->get_type(),
            'status' => (string) $product->get_status('edit'),
            'featured' => (bool) $product->get_featured('edit'),
            'catalog_visibility' => (string) $product->get_catalog_visibility('edit'),
            'description' => (string) $product->get_description('edit'),
            'short_description' => (string) $product->get_short_description('edit'),
            'sku' => (string) $product->get_sku('edit'),
            'price' => (string) $product->get_price('edit'),
            'regular_price' => (string) $product->get_regular_price('edit'),
            'sale_price' => (string) $product->get_sale_price('edit'),
            'price_html' => (string) $product->get_price_html(),
            'on_sale' => (bool) $product->is_on_sale('edit'),
            'purchasable' => (bool) $product->is_purchasable(),
            'total_sales' => (int) $product->get_total_sales('edit'),
            'virtual' => (bool) $product->get_virtual('edit'),
            'downloadable' => (bool) $product->get_downloadable('edit'),
            'downloads' => self::downloads($product),
            'download_limit' => (int) $product->get_download_limit('edit'),
            'download_expiry' => (int) $product->get_download_expiry('edit'),
            'external_url' => method_exists($product, 'get_product_url') ? (string) $product->get_product_url('edit') : '',
            'button_text' => method_exists($product, 'get_button_text') ? (string) $product->get_button_text('edit') : '',
            'tax_status' => (string) $product->get_tax_status('edit'),
            'tax_class' => (string) $product->get_tax_class('edit'),
            'manage_stock' => (bool) $product->get_manage_stock('edit'),
            'stock_quantity' => $product->get_stock_quantity('edit') === null ? null : (float) $product->get_stock_quantity('edit'),
            'stock_status' => (string) $product->get_stock_status('edit'),
            'backorders' => (string) $product->get_backorders('edit'),
            'backorders_allowed' => (bool) $product->backorders_allowed(),
            'backordered' => (bool) $product->is_on_backorder(1),
            'low_stock_amount' => method_exists($product, 'get_low_stock_amount') ? $product->get_low_stock_amount('edit') : '',
            'sold_individually' => (bool) $product->get_sold_individually('edit'),
            'inventory' => self::inventory($product),
            'weight' => $weight,
            'weight_grams' => self::weight_grams($weight),
            'dimensions' => [
                'length' => (string) $product->get_length('edit'),
                'width' => (string) $product->get_width('edit'),
                'height' => (string) $product->get_height('edit'),
            ],
            'shipping_required' => (bool) $product->needs_shipping(),
            'shipping_taxable' => (bool) $product->is_shipping_taxable(),
            'shipping_class' => (string) $product->get_shipping_class(),
            'shipping_class_id' => (int) $product->get_shipping_class_id(),
            'reviews_allowed' => (bool) $product->get_reviews_allowed('edit'),
            'average_rating' => (string) $product->get_average_rating('edit'),
            'rating_count' => (int) $product->get_rating_count('edit'),
            'purchase_note' => (string) $product->get_purchase_note('edit'),
            'categories' => self::terms($product->get_id(), 'product_cat'),
            'tags' => self::terms($product->get_id(), 'product_tag'),
            'images' => self::images($product),
            'attributes' => self::parent_attributes($product),
            'default_attributes' => self::default_attributes($product),
            'menu_order' => (int) $product->get_menu_order('edit'),
            'upsell_ids' => array_map('intval', $product->get_upsell_ids('edit')),
            'cross_sell_ids' => array_map('intval', $product->get_cross_sell_ids('edit')),
            'grouped_products' => method_exists($product, 'get_children') && $product->is_type('grouped') ? array_map('intval', $product->get_children('edit')) : [],
            'date_created' => self::date_value($product->get_date_created('edit'), false),
            'date_created_gmt' => self::date_value($product->get_date_created('edit'), true),
            'date_modified' => self::date_value($product->get_date_modified('edit'), false),
            'date_modified_gmt' => self::date_value($product->get_date_modified('edit'), true),
            'date_on_sale_from' => self::date_value($product->get_date_on_sale_from('edit'), false),
            'date_on_sale_from_gmt' => self::date_value($product->get_date_on_sale_from('edit'), true),
            'date_on_sale_to' => self::date_value($product->get_date_on_sale_to('edit'), false),
            'date_on_sale_to_gmt' => self::date_value($product->get_date_on_sale_to('edit'), true),
            'currency' => get_woocommerce_currency(),
            'meta_data' => self::mapping_meta($product),
            'variations' => [],
        ];

        if (method_exists($product, 'get_global_unique_id')) {
            $data['global_unique_id'] = (string) $product->get_global_unique_id('edit');
        }

        if ($product->is_type('variable')) {
            foreach ((array) $product->get_children() as $variation_id) {
                $variation = wc_get_product((int) $variation_id);
                if ($variation instanceof WC_Product_Variation) {
                    $data['variations'][] = self::variation_snapshot($variation);
                }
            }
        }

        return $data;
    }

    /**
     * Build nested full variation data.
     *
     * @param  WC_Product_Variation  $variation  Variation.
     * @return array
     */
    private static function variation_snapshot($variation)
    {
        $weight = (string) $variation->get_weight('edit');
        $image = self::image((int) $variation->get_image_id('edit'));

        $data = [
            'id' => (int) $variation->get_id(),
            'parent_id' => (int) $variation->get_parent_id('edit'),
            'name' => (string) $variation->get_name('edit'),
            'permalink' => (string) $variation->get_permalink(),
            'type' => 'variation',
            'status' => (string) $variation->get_status('edit'),
            'description' => (string) $variation->get_description('edit'),
            'sku' => (string) $variation->get_sku('edit'),
            'price' => (string) $variation->get_price('edit'),
            'regular_price' => (string) $variation->get_regular_price('edit'),
            'sale_price' => (string) $variation->get_sale_price('edit'),
            'on_sale' => (bool) $variation->is_on_sale('edit'),
            'purchasable' => (bool) $variation->is_purchasable(),
            'virtual' => (bool) $variation->get_virtual('edit'),
            'downloadable' => (bool) $variation->get_downloadable('edit'),
            'downloads' => self::downloads($variation),
            'download_limit' => (int) $variation->get_download_limit('edit'),
            'download_expiry' => (int) $variation->get_download_expiry('edit'),
            'tax_status' => (string) $variation->get_tax_status('edit'),
            'tax_class' => (string) $variation->get_tax_class('edit'),
            'manage_stock' => (bool) $variation->get_manage_stock('edit'),
            'stock_quantity' => $variation->get_stock_quantity('edit') === null ? null : (float) $variation->get_stock_quantity('edit'),
            'stock_status' => (string) $variation->get_stock_status('edit'),
            'backorders' => (string) $variation->get_backorders('edit'),
            'backorders_allowed' => (bool) $variation->backorders_allowed(),
            'backordered' => (bool) $variation->is_on_backorder(1),
            'low_stock_amount' => method_exists($variation, 'get_low_stock_amount') ? $variation->get_low_stock_amount('edit') : '',
            'sold_individually' => (bool) $variation->get_sold_individually('edit'),
            'inventory' => self::inventory($variation),
            'weight' => $weight,
            'weight_grams' => self::weight_grams($weight),
            'dimensions' => [
                'length' => (string) $variation->get_length('edit'),
                'width' => (string) $variation->get_width('edit'),
                'height' => (string) $variation->get_height('edit'),
            ],
            'shipping_required' => (bool) $variation->needs_shipping(),
            'shipping_taxable' => (bool) $variation->is_shipping_taxable(),
            'shipping_class' => (string) $variation->get_shipping_class(),
            'shipping_class_id' => (int) $variation->get_shipping_class_id(),
            'image' => $image,
            'attributes' => self::variation_attributes($variation),
            'menu_order' => (int) $variation->get_menu_order('edit'),
            'date_created' => self::date_value($variation->get_date_created('edit'), false),
            'date_created_gmt' => self::date_value($variation->get_date_created('edit'), true),
            'date_modified' => self::date_value($variation->get_date_modified('edit'), false),
            'date_modified_gmt' => self::date_value($variation->get_date_modified('edit'), true),
            'date_on_sale_from' => self::date_value($variation->get_date_on_sale_from('edit'), false),
            'date_on_sale_from_gmt' => self::date_value($variation->get_date_on_sale_from('edit'), true),
            'date_on_sale_to' => self::date_value($variation->get_date_on_sale_to('edit'), false),
            'date_on_sale_to_gmt' => self::date_value($variation->get_date_on_sale_to('edit'), true),
            'currency' => get_woocommerce_currency(),
            'meta_data' => self::mapping_meta($variation),
        ];

        if (method_exists($variation, 'get_global_unique_id')) {
            $data['global_unique_id'] = (string) $variation->get_global_unique_id('edit');
        }

        return $data;
    }

    /**
     * Explicit inventory block.
     *
     * @param  WC_Product  $product  Product.
     * @return array
     */
    private static function inventory($product)
    {
        return [
            'manage_stock' => (bool) $product->get_manage_stock('edit'),
            'quantity' => $product->get_stock_quantity('edit') === null ? null : (float) $product->get_stock_quantity('edit'),
            'status' => (string) $product->get_stock_status('edit'),
            'backorders' => (string) $product->get_backorders('edit'),
            'backorders_allowed' => (bool) $product->backorders_allowed(),
            'backordered' => (bool) $product->is_on_backorder(1),
            'low_stock_amount' => method_exists($product, 'get_low_stock_amount') ? $product->get_low_stock_amount('edit') : '',
        ];
    }

    /**
     * Convert configured store weight to grams.
     *
     * @param  string  $weight  Weight.
     * @return float|null
     */
    private static function weight_grams($weight)
    {
        if ($weight === '' || !is_numeric($weight)) {
            return null;
        }

        return (float) wc_get_weight((float) $weight, 'g', (string) get_option('woocommerce_weight_unit', 'kg'));
    }

    /**
     * Only persistent Adoology mapping metadata is exposed.
     *
     * @param  WC_Product  $product  Product.
     * @return array
     */
    private static function mapping_meta($product)
    {
        $meta = [];
        foreach (['_adoology_master_product_id', '_adoology_master_variant_id'] as $key) {
            $value = $product->get_meta($key, true, 'edit');
            if (is_scalar($value) && (string) $value !== '') {
                $meta[] = ['key' => $key, 'value' => (string) $value];
            }
        }

        return $meta;
    }

    /**
     * Product terms.
     *
     * @param  int  $product_id  Product ID.
     * @param  string  $taxonomy  Taxonomy.
     * @return array
     */
    private static function terms($product_id, $taxonomy)
    {
        $terms = get_the_terms((int) $product_id, $taxonomy);
        if (!is_array($terms)) {
            return [];
        }

        $result = [];
        foreach ($terms as $term) {
            $result[] = [
                'id' => (int) $term->term_id,
                'name' => (string) $term->name,
                'slug' => (string) $term->slug,
            ];
        }

        return $result;
    }

    /**
     * Parent attributes.
     *
     * @param  WC_Product  $product  Product.
     * @return array
     */
    private static function parent_attributes($product)
    {
        $result = [];
        foreach ($product->get_attributes('edit') as $attribute) {
            if (!$attribute instanceof WC_Product_Attribute) {
                continue;
            }
            $options = $attribute->is_taxonomy() ? array_map(fn ($term) => (string) $term->name, $attribute->get_terms()) : array_values(array_map('strval', $attribute->get_options()));

            $result[] = [
                'id' => (int) $attribute->get_id(),
                'name' => (string) wc_attribute_label($attribute->get_name(), $product),
                'slug' => (string) $attribute->get_name(),
                'position' => (int) $attribute->get_position(),
                'visible' => (bool) $attribute->get_visible(),
                'variation' => (bool) $attribute->get_variation(),
                'options' => $options,
            ];
        }

        return $result;
    }

    /**
     * Parent default attributes.
     *
     * @param  WC_Product  $product  Product.
     * @return array
     */
    private static function default_attributes($product)
    {
        $result = [];
        foreach ((array) $product->get_default_attributes('edit') as $name => $option) {
            $result[] = [
                'id' => (int) wc_attribute_taxonomy_id_by_name($name),
                'name' => (string) wc_attribute_label($name, $product),
                'slug' => (string) $name,
                'option' => (string) $option,
            ];
        }

        return $result;
    }

    /**
     * Variation attributes with human-readable term values.
     *
     * @param  WC_Product_Variation  $variation  Variation.
     * @return array
     */
    private static function variation_attributes($variation)
    {
        $result = [];
        foreach ((array) $variation->get_variation_attributes() as $key => $value) {
            $name = str_replace('attribute_', '', (string) $key);
            $option = (string) $value;
            if (taxonomy_exists($name)) {
                $term = get_term_by('slug', $option, $name);
                if ($term && !is_wp_error($term)) {
                    $option = (string) $term->name;
                }
            }
            $result[] = [
                'id' => (int) wc_attribute_taxonomy_id_by_name($name),
                'name' => (string) wc_attribute_label($name, $variation),
                'slug' => $name,
                'option' => $option,
            ];
        }

        return $result;
    }

    /**
     * Product download files.
     *
     * @param  WC_Product  $product  Product.
     * @return array
     */
    private static function downloads($product)
    {
        $result = [];
        foreach ((array) $product->get_downloads('edit') as $download) {
            if ($download instanceof WC_Product_Download) {
                $result[] = [
                    'id' => (string) $download->get_id(),
                    'name' => (string) $download->get_name(),
                    'file' => (string) $download->get_file(),
                ];
            }
        }

        return $result;
    }

    /**
     * Main and gallery image data.
     *
     * @param  WC_Product  $product  Product.
     * @return array
     */
    private static function images($product)
    {
        $ids = array_merge([(int) $product->get_image_id('edit')], array_map('intval', $product->get_gallery_image_ids('edit')));
        $ids = array_values(array_unique(array_filter($ids)));
        $out = [];
        foreach ($ids as $id) {
            $image = self::image($id);
            if ($image !== null) {
                $out[] = $image;
            }
        }

        return $out;
    }

    /**
     * One image allowlist.
     *
     * @param  int  $attachment_id  Attachment ID.
     * @return array|null
     */
    private static function image($attachment_id)
    {
        if ($attachment_id <= 0) {
            return null;
        }
        $src = wp_get_attachment_image_url($attachment_id, 'full');
        if (!$src) {
            return null;
        }
        $post = get_post($attachment_id);

        return [
            'id' => $attachment_id,
            'src' => (string) $src,
            'name' => $post ? (string) $post->post_title : '',
            'alt' => (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
            'date_created' => $post ? mysql_to_rfc3339($post->post_date) : null,
            'date_created_gmt' => $post ? mysql_to_rfc3339($post->post_date_gmt) : null,
            'date_modified' => $post ? mysql_to_rfc3339($post->post_modified) : null,
            'date_modified_gmt' => $post ? mysql_to_rfc3339($post->post_modified_gmt) : null,
        ];
    }

    /**
     * Format WC date like Woo REST API.
     *
     * @param  WC_DateTime|null  $date  Date.
     * @param  bool  $gmt  UTC output.
     * @return string|null
     */
    private static function date_value($date, $gmt)
    {
        return function_exists('wc_rest_prepare_date_response') ? wc_rest_prepare_date_response($date, $gmt) : null;
    }

    /**
     * Capture deletion type, parent relation, and full parent snapshot.
     *
     * @param  int  $post_id  Post ID.
     */
    private static function capture_deletion_context($post_id)
    {
        $post = get_post($post_id);
        if (!$post || !in_array($post->post_type, ['product', 'product_variation'], true)) {
            return;
        }

        self::$deletion_types[$post_id] = $post->post_type;
        set_transient(self::deletion_context_key($post_id), [
            'type' => $post->post_type,
            'parent_id' => (int) $post->post_parent,
        ], 2 * DAY_IN_SECONDS);

        if ($post->post_type === 'product_variation') {
            self::remember_variation($post_id, (int) $post->post_parent);

            return;
        }

        set_transient(self::parent_deleting_key($post_id), 1, 10 * MINUTE_IN_SECONDS);
        $product = wc_get_product($post_id);
        if ($product instanceof WC_Product && !$product->is_type('variation')) {
            $snapshot = self::product_snapshot($product);
            $snapshot['deleted_at_gmt'] = gmdate('Y-m-d\TH:i:s\Z');
            set_transient(self::deleted_snapshot_key($post_id), $snapshot, 2 * DAY_IN_SECONDS);
        }
    }

    /**
     * Persist parent relation through async delivery after variation deletion.
     *
     * @param  int  $variation_id  Variation ID.
     * @param  int  $parent_id  Parent ID.
     */
    private static function remember_variation($variation_id, $parent_id)
    {
        if ($variation_id <= 0 || $parent_id <= 0) {
            return;
        }
        self::$deletion_types[$variation_id] = 'product_variation';
        self::$variation_parents[$variation_id] = $parent_id;
        set_transient(self::variation_parent_key($variation_id), $parent_id, 2 * DAY_IN_SECONDS);
    }

    /**
     * Resource ID from native hook argument.
     *
     * @param  mixed  $arg  Argument.
     * @return int
     */
    private static function resource_id($arg)
    {
        return $arg instanceof WC_Product ? (int) $arg->get_id() : absint($arg);
    }

    /**
     * Product or variation type, including persisted deletion context.
     *
     * @param  int  $id  Resource ID.
     * @param  mixed  $arg  Original argument.
     * @return string
     */
    private static function resource_type($id, $arg = null)
    {
        if ($arg instanceof WC_Product) {
            return $arg->is_type('variation') ? 'product_variation' : 'product';
        }
        if (isset(self::$deletion_types[$id])) {
            return self::$deletion_types[$id];
        }

        $type = get_post_type($id);
        if (in_array($type, ['product', 'product_variation'], true)) {
            return $type;
        }

        $context = get_transient(self::deletion_context_key($id));

        return is_array($context) && isset($context['type']) ? (string) $context['type'] : '';
    }

    /**
     * Resolve parent product ID.
     *
     * @param  int  $id  Resource ID.
     * @param  mixed  $arg  Original argument.
     * @return int
     */
    private static function parent_product_id($id, $arg = null)
    {
        if (self::resource_type($id, $arg) !== 'product_variation') {
            return $id;
        }

        return self::variation_parent_id($id, $arg);
    }

    /**
     * Resolve variation parent before or after deletion.
     *
     * @param  int  $variation_id  Variation ID.
     * @param  mixed  $arg  Original argument.
     * @return int
     */
    private static function variation_parent_id($variation_id, $arg = null)
    {
        if ($arg instanceof WC_Product_Variation) {
            return (int) $arg->get_parent_id();
        }
        if (isset(self::$variation_parents[$variation_id])) {
            return (int) self::$variation_parents[$variation_id];
        }

        $parent_id = (int) wp_get_post_parent_id($variation_id);
        if ($parent_id <= 0) {
            $parent_id = (int) get_transient(self::variation_parent_key($variation_id));
        }
        if ($parent_id <= 0) {
            $context = get_transient(self::deletion_context_key($variation_id));
            $parent_id = is_array($context) && isset($context['parent_id']) ? (int) $context['parent_id'] : 0;
        }

        return $parent_id;
    }

    /**
     * Persist and schedule initial retry.
     *
     * @param  string  $event_id  Event ID.
     * @param  int  $webhook_id  Webhook ID.
     * @param  string  $body  Exact JSON body.
     */
    private static function queue_retry($event_id, $webhook_id, $body)
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', (string) $event_id) || $body === '' || strlen($body) > self::RETRY_MAX_BODY) {
            Logger::log('error', 'Webhook retry state rejected as invalid or too large.', ['webhook_id' => $webhook_id]);

            return;
        }

        $transient_key = self::retry_transient_key($event_id);
        if (is_array(get_transient($transient_key))) {
            return;
        }

        $index = Options::get('adoology_webhook_retry_index', []);
        $index = is_array($index) ? $index : [];
        if (count($index) >= self::RETRY_MAX_STATES) {
            asort($index, SORT_NUMERIC);
            $oldest = (string) key($index);
            if ($oldest !== '') {
                self::clear_retry($oldest);
                $index = Options::get('adoology_webhook_retry_index', []);
                $index = is_array($index) ? $index : [];
            }
        }

        set_transient($transient_key, [
            'webhook_id' => (int) $webhook_id,
            'body' => $body,
            'attempt' => 0,
            'created_at' => time(),
        ], 2 * DAY_IN_SECONDS);
        $index[$event_id] = time();
        Options::update('adoology_webhook_retry_index', $index);
        self::schedule_retry($event_id, 1);
    }

    /**
     * Schedule exponential retry delay.
     *
     * @param  string  $event_id  Event ID.
     * @param  int  $attempt  Next attempt.
     */
    private static function schedule_retry($event_id, $attempt)
    {
        $delays = [1 => 60, 2 => 300, 3 => 1800];
        $delay = $delays[$attempt] ?? 1800;
        $result = Scheduler::schedule_single(time() + $delay, self::RETRY_HOOK, [$event_id]);
        if (is_wp_error($result)) {
            self::clear_retry($event_id);
            Logger::log('error', 'Could not schedule an Adoology webhook retry.');
        }
    }

    /**
     * Clear retry payload, index, and pending action.
     *
     * @param  string  $event_id  Event ID.
     */
    private static function clear_retry($event_id)
    {
        if (!is_string($event_id) || $event_id === '') {
            return;
        }
        delete_transient(self::retry_transient_key($event_id));
        $index = Options::get('adoology_webhook_retry_index', []);
        if (is_array($index) && isset($index[$event_id])) {
            unset($index[$event_id]);
            Options::update('adoology_webhook_retry_index', $index);
        }
        Scheduler::unschedule(self::RETRY_HOOK, [$event_id]);
    }

    /** @return string */
    private static function retry_transient_key($event_id)
    {
        return 'adoology_retry_' . $event_id;
    }

    /** @return string */
    private static function deleted_snapshot_key($product_id)
    {
        return 'adoology_deleted_product_' . absint($product_id);
    }

    /** @return string */
    private static function variation_parent_key($variation_id)
    {
        return 'adoology_variation_parent_' . absint($variation_id);
    }

    /** @return string */
    private static function deletion_context_key($post_id)
    {
        return 'adoology_deletion_context_' . absint($post_id);
    }

    /** @return string */
    private static function parent_deleting_key($product_id)
    {
        return 'adoology_parent_deleting_' . absint($product_id);
    }
}
