<?php

/**
 * Crypto unit tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Crypto;
use Adoology\Tests\TestCase;
use WP_Error;

use function Brain\Monkey\Functions\when;

/**
 * @coversDefaultClass \Adoology\Crypto
 */
class CryptoTest extends TestCase
{
    /**
     * @covers ::is_available
     */
    public function test_is_available_with_openssl()
    {
        if (!Crypto::is_available()) {
            $this->markTestSkipped('AES-256-GCM unavailable in this runtime.');
        }
        $this->assertTrue(Crypto::is_available());
    }

    /**
     * @covers ::encrypt
     * @covers ::decrypt
     */
    public function test_encrypt_decrypt_roundtrip()
    {
        $this->stub_environment();

        $encrypted = Crypto::encrypt('workspace-secret', 'adoology_api_token');

        $this->assertIsString($encrypted);
        $this->assertStringStartsWith(Crypto::PREFIX, $encrypted);
        $this->assertSame('workspace-secret', Crypto::decrypt($encrypted, 'adoology_api_token'));
    }

    /**
     * @covers ::decrypt
     */
    public function test_decrypt_rejects_wrong_context()
    {
        $this->stub_environment();

        $encrypted = Crypto::encrypt('workspace-secret', 'adoology_api_token');

        $this->assertInstanceOf(WP_Error::class, Crypto::decrypt($encrypted, 'adoology_webhook_secret'));
    }

    /**
     * @covers ::decrypt
     */
    public function test_decrypt_rejects_tampered_ciphertext()
    {
        $this->stub_environment();

        $encrypted = Crypto::encrypt('workspace-secret', 'adoology_api_token');
        $inner = base64_decode(substr($encrypted, strlen(Crypto::PREFIX)), true);
        $data = json_decode($inner, true);
        $ciphertext = base64_decode($data['ct'], true);
        $ciphertext[0] = chr((ord($ciphertext[0]) + 1) % 256);
        $data['ct'] = base64_encode($ciphertext);
        $tampered = Crypto::PREFIX . base64_encode(wp_json_encode($data));

        $this->assertInstanceOf(WP_Error::class, Crypto::decrypt($tampered, 'adoology_api_token'));
    }

    /**
     * @covers ::decrypt
     */
    public function test_decrypt_rejects_plaintext()
    {
        $this->stub_environment();

        $this->assertInstanceOf(WP_Error::class, Crypto::decrypt('not-encrypted', 'adoology_api_token'));
    }

    /**
     * @covers ::get_secret
     * @covers ::set_secret
     */
    public function test_get_secret_migrates_legacy_plaintext()
    {
        $this->stub_environment();

        $options = ['adoology_api_token' => 'legacy-plaintext'];
        when('get_option')->alias(function ($name, $default = false) use (&$options) {
            return array_key_exists($name, $options) ? $options[$name] : $default;
        });
        when('update_option')->alias(function ($name, $value) use (&$options) {
            $options[$name] = $value;

            return true;
        });
        when('add_option')->alias(function ($name, $value) use (&$options) {
            $options[$name] = $value;

            return true;
        });

        $this->assertSame('legacy-plaintext', Crypto::get_secret('adoology_api_token'));
        $this->assertStringStartsWith(Crypto::PREFIX, $options['adoology_api_token']);
    }

    /**
     * @covers ::set_secret
     */
    public function test_set_secret_with_empty_value_deletes_option()
    {
        $this->stub_environment();

        $options = ['adoology_api_token' => 'old'];
        when('get_option')->alias(function ($name, $default = false) use (&$options) {
            return array_key_exists($name, $options) ? $options[$name] : $default;
        });
        when('delete_option')->alias(function ($name) use (&$options) {
            unset($options[$name]);

            return true;
        });
        when('update_option')->justReturn(true);
        when('add_option')->justReturn(true);

        $this->assertTrue(Crypto::set_secret('adoology_api_token', ''));
        $this->assertArrayNotHasKey('adoology_api_token', $options);
    }

    private function stub_environment()
    {
        when('__')->returnArg();
        when('wp_salt')->justReturn('test-auth-secret');
        when('get_current_blog_id')->justReturn(1);
    }
}
