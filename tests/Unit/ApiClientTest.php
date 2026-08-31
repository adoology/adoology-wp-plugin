<?php

/**
 * API client unit tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\ApiClient;
use Adoology\Tests\TestCase;
use WP_Error;

use function Brain\Monkey\Functions\when;

/**
 * @coversDefaultClass \Adoology\ApiClient
 */
class ApiClientTest extends TestCase
{
    /**
     * @covers ::is_valid_token
     */
    public function test_is_valid_token()
    {
        $this->assertTrue(ApiClient::is_valid_token('dc_abcdefgh'));
        $this->assertTrue(ApiClient::is_valid_token('dc_Ab1._~-xyz'));
        $this->assertFalse(ApiClient::is_valid_token('dc_short'));
        $this->assertFalse(ApiClient::is_valid_token('wrong_prefix_abcdefgh'));
        $this->assertFalse(ApiClient::is_valid_token(''));
        $this->assertFalse(ApiClient::is_valid_token('dc_' . str_repeat('a', 600)));
        $this->assertFalse(ApiClient::is_valid_token(123));
    }

    /**
     * @covers ::validate_base_url
     */
    public function test_validate_base_url_accepts_https()
    {
        $this->stub_url_helpers();

        $this->assertSame('https://api.adoology.com', ApiClient::validate_base_url('https://api.adoology.com'));
        $this->assertSame('https://api.adoology.com', ApiClient::validate_base_url('https://api.adoology.com/'));
    }

    /**
     * @covers ::validate_base_url
     */
    public function test_validate_base_url_rejects_empty_or_invalid()
    {
        $this->stub_url_helpers();

        $this->assertInstanceOf(WP_Error::class, ApiClient::validate_base_url(''));
        $this->assertInstanceOf(WP_Error::class, ApiClient::validate_base_url('not a url'));
    }

    /**
     * @covers ::validate_base_url
     */
    public function test_validate_base_url_rejects_plain_http()
    {
        $this->stub_url_helpers();

        $this->assertInstanceOf(WP_Error::class, ApiClient::validate_base_url('http://api.adoology.com'));
    }

    /**
     * @covers ::validate_base_url
     */
    public function test_validate_base_url_rejects_versioned_paths()
    {
        $this->stub_url_helpers();

        $this->assertInstanceOf(WP_Error::class, ApiClient::validate_base_url('https://api.adoology.com/api'));
        $this->assertInstanceOf(WP_Error::class, ApiClient::validate_base_url('https://api.adoology.com/api/v1'));
        $this->assertInstanceOf(WP_Error::class, ApiClient::validate_base_url('https://api.adoology.com/v1'));
    }

    /**
     * @covers ::validate_base_url
     */
    public function test_validate_base_url_rejects_credentials_query_fragment()
    {
        $this->stub_url_helpers();

        $this->assertInstanceOf(WP_Error::class, ApiClient::validate_base_url('https://user:pass@api.adoology.com'));
        $this->assertInstanceOf(WP_Error::class, ApiClient::validate_base_url('https://api.adoology.com?x=1'));
        $this->assertInstanceOf(WP_Error::class, ApiClient::validate_base_url('https://api.adoology.com#frag'));
    }

    /**
     * @covers ::error_status
     */
    public function test_error_status_extracts_status()
    {
        $this->assertSame(0, ApiClient::error_status(null));
        $this->assertSame(0, ApiClient::error_status(new WP_Error('x', 'msg')));
        $this->assertSame(404, ApiClient::error_status(new WP_Error('x', 'msg', ['status' => 404])));
    }

    private function stub_url_helpers()
    {
        when('__')->returnArg();
        when('untrailingslashit')->alias(fn ($value) => rtrim((string) $value, '/'));
        when('wp_http_validate_url')->alias(fn ($url) => filter_var($url, FILTER_VALIDATE_URL) ? $url : false);
        when('wp_parse_url')->alias(fn ($url, $component = -1) => parse_url($url, $component));
        when('esc_url_raw')->returnArg();
    }
}
