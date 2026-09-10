<?php

/**
 * Connection identifier unit tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Connection;
use Adoology\Crypto;
use Adoology\Options;
use Adoology\Tests\TestCase;
use Mockery;
use ReflectionMethod;
use stdClass;

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
        $options = ['adoology_connection_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV'];
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
            $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', json_decode($arguments['body'], true)['existing_connection_id']);

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
                    'meta' => [
                        'existing' => true,
                        'webhook_secret' => str_repeat('a', 64),
                    ],
                ]),
            ];
        });
        when('wp_remote_retrieve_response_code')->alias(fn ($response) => $response['response']['code']);
        when('wp_remote_retrieve_body')->alias(fn ($response) => $response['body']);
        when('wp_remote_retrieve_header')->alias(fn ($response, $header) => $response['headers'][strtolower($header)] ?? '');

        $this->assertTrue(Crypto::set_secret('adoology_api_token', 'dc_workspace-token'));
        $this->assertTrue(Connection::connect());
        $this->assertSame('01ARZ3NDEKTSV4RRFFQ69G5FAV', Options::get('adoology_connection_id'));
        $this->assertSame(str_repeat('a', 64), Crypto::get_secret('adoology_webhook_secret'));
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

    /**
     * @covers ::connect
     */
    public function test_connect_resumes_an_interrupted_authorization()
    {
        $connection_id = '01ARZ3NDEKTSV4RRFFQ69G5FAV';
        $webhook_secret = str_repeat('a', 64);
        /** @var array<string, mixed> $options */
        $options = ['adoology_connection_id' => $connection_id];
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

        $authorization_url = 'https://woo.test/wc-auth/v1/authorize?' . http_build_query([
            'scope' => 'read_write',
            'user_id' => $connection_id . '.' . hash_hmac('sha256', $connection_id, $webhook_secret),
            'callback_url' => 'https://api.adoology.com/woocommerce/callback',
        ]);
        $requests = 0;
        when('wp_safe_remote_request')->alias(function ($url, $arguments) use (&$requests, $authorization_url, $connection_id, $webhook_secret) {
            $requests++;
            $this->assertSame('https://api.adoology.com/v1/channel-connections' . ($requests === 1 ? '/' . $connection_id : ''), $url);
            $this->assertSame($requests === 1 ? 'GET' : 'POST', $arguments['method']);

            return [
                'response' => ['code' => 200],
                'headers' => ['content-type' => 'application/json'],
                'body' => wp_json_encode([
                    'data' => [
                        'id' => $connection_id,
                        'attributes' => [
                            'status' => 'connecting',
                            'base_url' => 'https://woo.test',
                        ],
                    ],
                    'meta' => $requests === 2 ? [
                        'existing' => true,
                        'webhook_secret' => $webhook_secret,
                        'redirect_uri' => $authorization_url,
                    ] : [],
                ]),
            ];
        });
        when('wp_remote_retrieve_response_code')->alias(fn ($response) => $response['response']['code']);
        when('wp_remote_retrieve_body')->alias(fn ($response) => $response['body']);
        when('wp_remote_retrieve_header')->alias(fn ($response, $header) => $response['headers'][strtolower($header)] ?? '');

        $this->assertTrue(Crypto::set_secret('adoology_api_token', 'dc_workspace-token'));
        $this->assertTrue(Crypto::set_secret('adoology_webhook_secret', $webhook_secret));
        $this->assertSame($authorization_url, Connection::connect());
        $this->assertSame(2, $requests);
    }

    /**
     * @covers ::delete_managed_api_keys
     */
    public function test_delete_managed_api_keys_removes_native_suffixed_key_descriptions()
    {
        global $wpdb;

        $wpdb = Mockery::mock(stdClass::class);
        $wpdb->prefix = 'wp_';
        $wpdb->shouldReceive('esc_like')->andReturnUsing(static fn ($value): string => addcslashes((string) $value, '_%\\'));
        $wpdb->shouldReceive('prepare')->andReturnUsing(static fn (string $sql): string => $sql);
        $wpdb->shouldReceive('get_results')->andReturn([
            ['key_id' => 7, 'description' => 'Adoology Connector 01ARZ3NDEKTSV4RRFFQ69G5FAV - API (2026-09-09 10:00:00)'],
            ['key_id' => 8, 'description' => 'Adoology Connector 01ARZ3NDEKTSV4RRFFQ69G5FAV'],
            ['key_id' => 9, 'description' => 'Adoology - API (2026-01-01 00:00:00)'],
            ['key_id' => 10, 'description' => 'Some other plugin key'],
        ]);

        $deleted = [];
        $wpdb->shouldReceive('delete')->andReturnUsing(static function ($table, $where) use (&$deleted) {
            $deleted[] = $where;

            return 1;
        });

        when('get_option')->justReturn(false);

        $method = new ReflectionMethod(Connection::class, 'delete_managed_api_keys');
        $method->setAccessible(true);
        $method->invoke(null);

        $this->assertSame([7, 8, 9], array_column($deleted, 'key_id'));
    }
}
