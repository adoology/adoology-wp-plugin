<?php

/**
 * Incomplete checkout event payload tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Crypto;
use Adoology\IncompleteOrders;
use Adoology\Tests\TestCase;
use ReflectionMethod;
use stdClass;

use function Brain\Monkey\Functions\when;

/**
 * @coversDefaultClass \Adoology\IncompleteOrders
 */
class IncompleteOrdersTest extends TestCase
{
    /**
     * @covers ::incomplete_event_properties
     */
    public function test_incomplete_event_contains_product_name()
    {
        $product = new class
        {
            public function get_name()
            {
                return 'Premium Cotton Shirt';
            }
        };
        when('wc_get_product')->justReturn($product);

        $method = new ReflectionMethod(IncompleteOrders::class, 'incomplete_event_properties');
        $method->setAccessible(true);
        $properties = $method->invoke(null, [
            'checkout_id' => 'checkout-1',
            'flow' => 'checkout',
            'product_id' => 30,
            'variation_id' => 31,
            'quantity' => 2,
            'value_minor' => 250000,
            'currency' => 'BDT',
            'form_stage' => 'details',
            'customer_data' => '',
        ]);

        $this->assertSame(30, $properties['product_id']);
        $this->assertSame(31, $properties['variation_id']);
        $this->assertSame('Premium Cotton Shirt', $properties['product_name']);
    }

    /**
     * @covers ::incomplete_event_properties
     */
    public function test_incomplete_event_contains_all_cart_items_with_product_names()
    {
        when('__')->returnArg();
        when('wp_salt')->justReturn('test-auth-secret');
        when('get_current_blog_id')->justReturn(1);
        when('wc_get_product')->alias(function ($product_id) {
            $names = [30 => 'Premium Cotton Shirt', 41 => 'Leather Wallet'];

            return isset($names[$product_id]) ? new class($names[$product_id])
            {
                private $name;

                public function __construct($name)
                {
                    $this->name = $name;
                }

                public function get_name()
                {
                    return $this->name;
                }
            } : false;
        });
        $customer_data = Crypto::encrypt(wp_json_encode([
            'items' => [
                ['product_id' => 30, 'variation_id' => 0, 'quantity' => 2],
                ['product_id' => 40, 'variation_id' => 41, 'quantity' => 1],
            ],
        ]), 'adoology_checkout_checkout-1');

        $method = new ReflectionMethod(IncompleteOrders::class, 'incomplete_event_properties');
        $method->setAccessible(true);
        $properties = $method->invoke(null, [
            'checkout_id' => 'checkout-1',
            'flow' => 'checkout',
            'product_id' => 30,
            'variation_id' => 0,
            'quantity' => 2,
            'value_minor' => 250000,
            'currency' => 'BDT',
            'form_stage' => 'details',
            'customer_data' => $customer_data,
        ]);

        $this->assertSame([
            [
                'product_id' => 30,
                'variation_id' => 0,
                'quantity' => 2,
                'product_name' => 'Premium Cotton Shirt',
            ],
            [
                'product_id' => 40,
                'variation_id' => 41,
                'quantity' => 1,
                'product_name' => 'Leather Wallet',
            ],
        ], $properties['items']);
    }

    /**
     * @covers ::trusted_capture_data
     */
    public function test_checkout_capture_uses_server_cart_instead_of_submitted_values()
    {
        $cart = new class
        {
            public function get_cart()
            {
                return [[
                    'product_id' => 30,
                    'variation_id' => 31,
                    'quantity' => 2,
                ]];
            }

            public function get_total($context)
            {
                return '25.00';
            }
        };
        when('WC')->justReturn((object) ['session' => new stdClass, 'cart' => $cart]);
        when('wc_get_price_decimals')->justReturn(2);
        when('get_woocommerce_currency')->justReturn('BDT');

        $method = new ReflectionMethod(IncompleteOrders::class, 'trusted_capture_data');
        $method->setAccessible(true);
        $result = $method->invoke(null, ['flow' => 'checkout'], [
            'product_id' => 999,
            'quantity' => 99,
            'value_minor' => 1,
            'currency' => 'USD',
        ]);

        $this->assertSame(30, $result['product_id']);
        $this->assertSame(31, $result['variation_id']);
        $this->assertSame(2, $result['quantity']);
        $this->assertSame(2500, $result['value_minor']);
        $this->assertSame('BDT', $result['currency']);
    }

    /**
     * @covers ::trusted_capture_data
     */
    public function test_checkout_capture_loads_cart_for_custom_rest_route()
    {
        $cart = new class
        {
            public function get_cart()
            {
                return [['product_id' => 30, 'variation_id' => 0, 'quantity' => 1]];
            }

            public function get_total($context)
            {
                return '10.00';
            }
        };
        $woocommerce = (object) ['session' => null, 'cart' => null];
        when('WC')->justReturn($woocommerce);
        when('wc_load_cart')->alias(function () use ($woocommerce, $cart) {
            $woocommerce->session = new stdClass;
            $woocommerce->cart = $cart;
        });
        when('wc_get_price_decimals')->justReturn(2);
        when('get_woocommerce_currency')->justReturn('BDT');

        $method = new ReflectionMethod(IncompleteOrders::class, 'trusted_capture_data');
        $method->setAccessible(true);
        $result = $method->invoke(null, ['flow' => 'checkout'], []);

        $this->assertSame(30, $result['product_id']);
        $this->assertSame(1000, $result['value_minor']);
    }

    /**
     * @covers ::trusted_capture_data
     */
    public function test_order_form_capture_is_bound_to_signed_parent_product()
    {
        $variation = new class
        {
            public function is_purchasable()
            {
                return true;
            }

            public function get_parent_id()
            {
                return 30;
            }

            public function get_price()
            {
                return '12.50';
            }
        };
        when('wc_get_product')->justReturn($variation);
        when('wc_get_price_decimals')->justReturn(2);
        when('get_woocommerce_currency')->justReturn('BDT');

        $method = new ReflectionMethod(IncompleteOrders::class, 'trusted_capture_data');
        $method->setAccessible(true);
        $result = $method->invoke(null, ['flow' => 'order_form', 'product_id' => 30], [
            'product_id' => 999,
            'variation_id' => 31,
            'quantity' => 2,
            'value_minor' => 1,
        ]);

        $this->assertSame(30, $result['product_id']);
        $this->assertSame(31, $result['variation_id']);
        $this->assertSame(2500, $result['value_minor']);
        $this->assertSame([[
            'product_id' => 30,
            'variation_id' => 31,
            'quantity' => 2,
        ]], $result['items']);
    }

    /**
     * @covers ::verify_capture_context
     */
    public function test_capture_context_rejects_tampering()
    {
        when('wp_salt')->justReturn('test-nonce-secret');
        $encoded = base64_encode(wp_json_encode([
            'flow' => 'order_form',
            'product_id' => 30,
            'landing_page' => 'https://woo.test/order',
            'expires' => time() + HOUR_IN_SECONDS,
        ]));
        $signature = hash_hmac('sha256', $encoded, 'test-nonce-secret');
        $method = new ReflectionMethod(IncompleteOrders::class, 'verify_capture_context');
        $method->setAccessible(true);

        $this->assertIsArray($method->invoke(null, $encoded, $signature));
        $this->assertFalse($method->invoke(null, $encoded . 'x', $signature));
    }

    /**
     * @covers ::identity_for_checkout
     */
    public function test_order_form_submission_recovers_signed_identity_before_initial_snapshot_finishes()
    {
        $GLOBALS['wpdb'] = new class
        {
            public $prefix = 'wp_';

            public function prepare($query, ...$values)
            {
                return $query;
            }

            public function get_row($query, $format)
            {
                return null;
            }
        };
        when('wp_salt')->justReturn('test-nonce-secret');
        $anonymous_id = '12345678-1234-4234-8234-123456789abc';
        $checkout_id = '22345678-1234-4234-8234-123456789abc';
        $session_id = '32345678-1234-4234-8234-123456789abc';
        $context = base64_encode(wp_json_encode([
            'flow' => 'order_form',
            'product_id' => 30,
            'instance' => '42345678-1234-4234-8234-123456789abc',
            'landing_page' => 'https://woo.test/order',
            'expires' => time() + HOUR_IN_SECONDS,
        ]));
        $expires = time() + HOUR_IN_SECONDS;
        $message = $anonymous_id . '|' . $checkout_id . '|' . $session_id . '|' . hash('sha256', $context) . '|' . $expires;

        $identity = IncompleteOrders::identity_for_checkout($checkout_id, [
            'anonymous_id' => $anonymous_id,
            'session_id' => $session_id,
            'capture_token' => $expires . '.' . hash_hmac('sha256', $message, 'test-nonce-secret'),
            'capture_context' => $context,
            'capture_signature' => hash_hmac('sha256', $context, 'test-nonce-secret'),
        ], 30);

        $this->assertSame([
            'anonymous_id' => $anonymous_id,
            'checkout_id' => $checkout_id,
            'session_id' => $session_id,
        ], $identity);
        unset($GLOBALS['wpdb']);
    }
}
