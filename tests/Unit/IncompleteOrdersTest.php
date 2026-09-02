<?php

/**
 * Incomplete checkout event payload tests.
 */

namespace Adoology\Tests\Unit;

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
}
