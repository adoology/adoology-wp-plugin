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
    public function test_incomplete_event_contains_product_name_and_thumbnail()
    {
        $product = new class
        {
            public function get_name()
            {
                return 'Premium Cotton Shirt';
            }

            public function get_image_id()
            {
                return 55;
            }
        };
        when('wc_get_product')->justReturn($product);
        when('esc_url_raw')->returnArg();
        when('wp_get_attachment_image_url')->alias(fn ($id) => 'https://woo.test/wp-content/uploads/img-' . $id . '-300x300.png');

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
        $this->assertSame('https://woo.test/wp-content/uploads/img-55-300x300.png', $properties['product_image']);
    }

    /**
     * @covers ::incomplete_event_properties
     */
    public function test_incomplete_event_contains_all_cart_items_with_product_names_and_thumbnails()
    {
        when('__')->returnArg();
        when('wp_salt')->justReturn('test-auth-secret');
        when('get_current_blog_id')->justReturn(1);
        when('esc_url_raw')->returnArg();
        when('wp_get_attachment_image_url')->alias(fn ($id) => 'https://woo.test/wp-content/uploads/img-' . $id . '-300x300.png');
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

                public function get_image_id()
                {
                    return 66;
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
                'product_image' => 'https://woo.test/wp-content/uploads/img-66-300x300.png',
            ],
            [
                'product_id' => 40,
                'variation_id' => 41,
                'quantity' => 1,
                'product_name' => 'Leather Wallet',
                'product_image' => 'https://woo.test/wp-content/uploads/img-66-300x300.png',
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

    public function test_stored_identity_requires_matching_signed_proof()
    {
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

        when('wp_salt')->justReturn('test-nonce-secret');
        when('wp_generate_password')->justReturn('0123456789abcdef0123456789abcdef');
        when('get_current_blog_id')->justReturn(1);
        when('wp_generate_uuid4')->justReturn('72345678-1234-4234-8234-123456789abc');
        when('wp_unslash')->returnArg();
        when('WC')->justReturn((object) ['session' => null]);
        $_COOKIE[IncompleteOrders::COOKIE_ANON] = '82345678-1234-4234-8234-123456789abc';
        $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] = '92345678-1234-4234-8234-123456789abc';
        $encryptedCustomer = Crypto::encrypt(wp_json_encode(['anonymous_id' => $anonymous_id]), 'adoology_checkout_' . $checkout_id);

        $makeWpdb = static fn (): object => new class($encryptedCustomer, $session_id)
        {
            public $prefix = 'wp_';

            public function __construct(private string $customerData, private string $sessionId) {}

            public function prepare($query, ...$values)
            {
                return $query;
            }

            public function get_row($query, $format)
            {
                return ['session_id' => $this->sessionId, 'customer_data' => $this->customerData];
            }
        };

        $GLOBALS['wpdb'] = $makeWpdb();

        $stored = IncompleteOrders::identity_for_checkout($checkout_id, [
            'anonymous_id' => $anonymous_id,
            'session_id' => $session_id,
            'capture_token' => $expires . '.' . hash_hmac('sha256', $message, 'test-nonce-secret'),
            'capture_context' => $context,
            'capture_signature' => hash_hmac('sha256', $context, 'test-nonce-secret'),
        ], 30);
        $this->assertSame($anonymous_id, $stored['anonymous_id']);

        // A caller holding only the checkout UUID must not recover the
        // stored identity.
        $proofless = IncompleteOrders::identity_for_checkout($checkout_id, [], 30);
        $this->assertNotSame($anonymous_id, $proofless['anonymous_id']);

        // Even valid proof carrying different ids must not claim the row.
        $foreign = '52345678-1234-4234-8234-123456789abc';
        $foreignSession = '62345678-1234-4234-8234-123456789abc';
        $foreignMessage = $foreign . '|' . $checkout_id . '|' . $foreignSession . '|' . hash('sha256', $context) . '|' . $expires;
        $hijack = IncompleteOrders::identity_for_checkout($checkout_id, [
            'anonymous_id' => $foreign,
            'session_id' => $foreignSession,
            'capture_token' => $expires . '.' . hash_hmac('sha256', $foreignMessage, 'test-nonce-secret'),
            'capture_context' => $context,
            'capture_signature' => hash_hmac('sha256', $context, 'test-nonce-secret'),
        ], 30);
        $this->assertNotSame($anonymous_id, $hijack['anonymous_id']);

        unset($GLOBALS['wpdb'], $_COOKIE[IncompleteOrders::COOKIE_ANON], $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]);
    }

    public function test_client_identifier_prefers_customer_then_ip_over_cookies()
    {
        when('wp_salt')->justReturn('test-nonce-secret');
        when('wp_unslash')->returnArg();

        $session = new class
        {
            public function get_customer_id()
            {
                return 42;
            }
        };

        $woo = (object) ['session' => $session];
        when('WC')->justReturn($woo);
        $this->assertSame('customer:42', IncompleteOrders::client_identifier());

        when('WC')->justReturn((object) ['session' => null]);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $ipSubject = IncompleteOrders::client_identifier();
        $this->assertStringStartsWith('ip:', $ipSubject);

        // Clearing cookies does not change the IP-bound subject.
        $_COOKIE[IncompleteOrders::COOKIE_ANON] = '82345678-1234-4234-8234-123456789abc';
        $this->assertSame($ipSubject, IncompleteOrders::client_identifier());

        unset($_SERVER['REMOTE_ADDR'], $_COOKIE[IncompleteOrders::COOKIE_ANON]);
    }
}
