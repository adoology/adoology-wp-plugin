<?php

/**
 * Connection identifier unit tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Connection;
use Adoology\Crypto;
use Adoology\Options;
use Adoology\Tests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @coversDefaultClass \Adoology\Connection
 */
class ConnectionTest extends TestCase
{
    /**
     * @covers ::is_valid_connection_id
     */
    public function test_accepts_26_char_ulid_alphabet()
    {
        $this->assertTrue(Connection::is_valid_connection_id('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
    }

    /**
     * @covers ::is_valid_connection_id
     */
    public function test_rejects_malformed_ids()
    {
        $this->assertFalse(Connection::is_valid_connection_id('01ARZ3NDEKTSV4RRFFQ69G5FA'));
        $this->assertFalse(Connection::is_valid_connection_id('01ARZ3NDEKTSV4RRFFQ69G5FAVA'));
        $this->assertFalse(Connection::is_valid_connection_id('01ARZ3NDEKTSV4RRFFQ69G5FAU'));
        $this->assertFalse(Connection::is_valid_connection_id(''));
        $this->assertFalse(Connection::is_valid_connection_id(123));
    }

    /**
     * @covers ::is_valid_connection_id
     */
    public function test_rejects_disallowed_characters()
    {
        $this->assertFalse(Connection::is_valid_connection_id('0IARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertFalse(Connection::is_valid_connection_id('0LARZ3NDEKTSV4RRFFQ69G5FAV'));
        $this->assertFalse(Connection::is_valid_connection_id('0*ARZ3NDEKTSV4RRFFQ69G5FAV'));
    }

    /**
     * @covers ::connect
     */
    public function test_connect_restores_existing_backend_connection_information()
    {
        /** @var array<string, mixed> $options */
        $options = [];
        when('__')->returnArg();
        when('wp_salt')->justReturn('test-auth-secret');
        when('get_current_blog_id')->justReturn(1);
        when('get_option')->alias(function ($name, $default = false) use (&$options) {
            return array_key_exists($name, $options) ? $options[$name] : $default;
        });
        when('add_option')->alias(function ($name, $value) use (&$options) {
            $options[$name] = $value;

            return true;
        });
        when('update_option')->alias(function ($name, $value) use (&$options) {
            $options[$name] = $value;

            return true;
        });
        when('delete_option')->alias(function ($name) use (&$options) {
            unset($options[$name]);

            return true;
        });
        when('untrailingslashit')->alias(fn ($value) => rtrim((string) $value, '/'));
        when('wp_http_validate_url')->alias(fn ($url) => filter_var($url, FILTER_VALIDATE_URL) ? $url : false);
        when('wp_parse_url')->alias(fn ($url, $component = -1) => parse_url($url, $component));
        when('esc_url_raw')->returnArg();
        when('wp_generate_uuid4')->justReturn('12345678-1234-4234-8234-123456789abc');
        when('get_bloginfo')->justReturn('Local Store');
        when('wp_specialchars_decode')->returnArg();
        when('home_url')->alias(fn ($path = '') => 'https://woo.test' . $path);
        when('wp_timezone_string')->justReturn('UTC');
        when('get_woocommerce_currency')->justReturn('USD');
        when('rest_url')->alias(fn ($path = '') => 'https://woo.test/wp-json/' . $path);
        when('wp_safe_remote_request')->alias(function ($url, $arguments) {
            $this->assertSame('https://api.adoology.com/v1/channel-connections', $url);
            $this->assertSame('POST', $arguments['method']);

            return [
                'response' => ['code' => 200],
                'headers' => ['content-type' => 'application/json'],
                'body' => wp_json_encode([
                    'data' => [
                        'id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
                        'attributes' => [
                            'name' => 'Existing Store',
                            'status' => 'active',
                            'base_url' => 'https://woo.test',
                            'timezone' => 'Asia/Dhaka',
                            'presentment_currency' => 'BDT',
                            'error_count' => 0,
                        ],
                    ],
                    'meta' => ['existing' => true],
                ]),
            ];
        });
        when('wp_remote_retrieve_response_code')->alias(fn ($response) => $response['response']['code']);
        when('wp_remote_retrieve_body')->alias(fn ($response) => $response['body']);
        when('wp_remote_retrieve_header')->alias(fn ($response, $header) => $response['headers'][strtolower($header)] ?? '');

        $this->assertTrue(Crypto::set_secret('adoology_api_token', 'dc_workspace-token'));
        $this->assertTrue(Connection::connect());
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', Options::get('adoology_connection_id'));
        $this->assertSame([
            'status' => 'active',
            'checked_at' => gmdate('Y-m-d H:i:s'),
            'name' => 'Existing Store',
            'base_url' => 'https://woo.test',
            'timezone' => 'Asia/Dhaka',
            'presentment_currency' => 'BDT',
            'error_count' => 0,
        ], Options::get('adoology_connection_state'));
        $this->assertArrayNotHasKey('adoology_create_idempotency_key', $options);
    }
}
