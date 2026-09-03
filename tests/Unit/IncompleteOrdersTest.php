<?php

/**
 * Incomplete checkout event payload tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Crypto;
use Adoology\IncompleteOrders;
use Adoology\Tests\TestCase;
use ReflectionMethod;

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
}
