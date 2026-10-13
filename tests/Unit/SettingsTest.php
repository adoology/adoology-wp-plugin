<?php

namespace Adoology\Tests\Unit;

use Adoology\Settings;
use Adoology\Tests\TestCase;
use Mockery;
use ReflectionClass;
use RuntimeException;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class SettingsTest extends TestCase
{
    /**
     * @dataProvider rejected_retry_requests
     */
    public function test_rejected_retry_request_cannot_mutate_event_queue($method, $can_manage, $valid_nonce)
    {
        global $wpdb;

        $previous_database = $wpdb;
        $previous_method = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD'] = $method;
        $wpdb = Mockery::mock();
        $wpdb->shouldNotReceive('query');

        when('current_user_can')->justReturn($can_manage);
        when('wp_unslash')->returnArg();
        when('sanitize_text_field')->returnArg();
        when('esc_html__')->returnArg();
        when('wp_die')->alias(function () {
            throw new RuntimeException('Request rejected');
        });
        if ($can_manage && $method === 'POST') {
            expect('check_admin_referer')->once()->with('adoology_retry_events')->andReturnUsing(function () use ($valid_nonce) {
                if (!$valid_nonce) {
                    throw new RuntimeException('Request rejected');
                }

                return 1;
            });
        } else {
            expect('check_admin_referer')->never();
        }

        $settings = (new ReflectionClass(Settings::class))->newInstanceWithoutConstructor();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Request rejected');

        try {
            $settings->handle_retry_events();
        } finally {
            $wpdb = $previous_database;
            if ($previous_method === null) {
                unset($_SERVER['REQUEST_METHOD']);
            } else {
                $_SERVER['REQUEST_METHOD'] = $previous_method;
            }
        }
    }

    public static function rejected_retry_requests()
    {
        return [
            'missing capability' => ['POST', false, true],
            'GET cannot mutate' => ['GET', true, true],
            'invalid nonce' => ['POST', true, false],
        ];
    }
}
