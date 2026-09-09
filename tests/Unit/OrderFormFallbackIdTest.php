<?php

/**
 * Fallback checkout identity stability regressions.
 */

namespace Adoology\Tests\Unit;

use Adoology\IncompleteOrders;
use Adoology\OrderForm;
use Adoology\Tests\TestCase;
use ReflectionMethod;

use function Brain\Monkey\Functions\when;

/**
 * @coversDefaultClass \Adoology\OrderForm
 */
class OrderFormFallbackIdTest extends TestCase
{
    /**
     * @covers ::fallback_checkout_id via reflection
     */
    public function test_fallback_id_is_stable_across_page_regenerations()
    {
        when('wp_salt')->justReturn('fallback-secret');
        when('wp_unslash')->returnArg();
        when('sanitize_text_field')->returnArg();
        IncompleteOrders::client_ip();

        $method = new ReflectionMethod(OrderForm::class, 'fallback_checkout_id');
        $method->setAccessible(true);

        $first = $method->invoke(null, 'nonce-one', '+8801712345678', 30);
        $second = $method->invoke(null, 'nonce-two', '+8801712345678', 30);

        $this->assertSame($first, $second);
        $this->assertNotSame($first, $method->invoke(null, 'nonce-one', '+8801899999999', 30));
    }
}
