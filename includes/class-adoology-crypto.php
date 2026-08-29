<?php
/**
 * Authenticated encryption for locally retained credentials.
 *
 * @package Adoology_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

class Adoology_Crypto {

    const PREFIX = 'ado:gcm:1:';
    const CIPHER = 'aes-256-gcm';

    /**
     * Check required cryptography support.
     *
     * @return bool
     */
    public static function is_available() {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt') || !function_exists('random_bytes')) {
            return false;
        }

        return in_array(self::CIPHER, array_map('strtolower', openssl_get_cipher_methods()), true);
    }

    /**
     * Encrypt a value with option-specific authenticated data.
     *
     * @param string $plaintext Plaintext.
     * @param string $context   Storage context.
     * @return string|WP_Error
     */
    public static function encrypt($plaintext, $context) {
        if (!self::is_available()) {
            return new WP_Error('adoology_crypto_unavailable', __('OpenSSL AES-256-GCM support is required to store Adoology credentials.', 'adoology-connector'));
        }

        try {
            $iv = random_bytes(12);
        } catch (Exception $exception) {
            return new WP_Error('adoology_random_failed', __('Secure random generation failed.', 'adoology-connector'));
        }

        $tag        = '';
        $ciphertext = openssl_encrypt(
            (string) $plaintext,
            self::CIPHER,
            self::key($context),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::aad($context),
            16
        );

        if ($ciphertext === false || strlen($tag) !== 16) {
            return new WP_Error('adoology_encrypt_failed', __('Credential encryption failed.', 'adoology-connector'));
        }

        $payload = wp_json_encode(array(
            'iv'  => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ct'  => base64_encode($ciphertext),
        ));

        if (!is_string($payload)) {
            return new WP_Error('adoology_encrypt_failed', __('Credential encryption failed.', 'adoology-connector'));
        }

        return self::PREFIX . base64_encode($payload);
    }

    /**
     * Decrypt and authenticate a value.
     *
     * @param string $encoded Encrypted value.
     * @param string $context Storage context.
     * @return string|WP_Error
     */
    public static function decrypt($encoded, $context) {
        if (!self::is_available()) {
            return new WP_Error('adoology_crypto_unavailable', __('OpenSSL AES-256-GCM support is required to read Adoology credentials.', 'adoology-connector'));
        }

        if (strpos((string) $encoded, self::PREFIX) !== 0) {
            return new WP_Error('adoology_ciphertext_invalid', __('Stored Adoology credential is not encrypted.', 'adoology-connector'));
        }

        $json = base64_decode(substr((string) $encoded, strlen(self::PREFIX)), true);
        $data = is_string($json) ? json_decode($json, true) : null;

        if (!is_array($data) || !isset($data['iv'], $data['tag'], $data['ct'])) {
            return new WP_Error('adoology_ciphertext_invalid', __('Stored Adoology credential is invalid.', 'adoology-connector'));
        }

        $iv         = base64_decode((string) $data['iv'], true);
        $tag        = base64_decode((string) $data['tag'], true);
        $ciphertext = base64_decode((string) $data['ct'], true);

        if (!is_string($iv) || strlen($iv) !== 12 || !is_string($tag) || strlen($tag) !== 16 || !is_string($ciphertext)) {
            return new WP_Error('adoology_ciphertext_invalid', __('Stored Adoology credential is invalid.', 'adoology-connector'));
        }

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            self::key($context),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::aad($context)
        );

        if (!is_string($plaintext)) {
            return new WP_Error('adoology_decrypt_failed', __('Stored Adoology credential could not be decrypted. WordPress salts may have changed.', 'adoology-connector'));
        }

        return $plaintext;
    }

    /**
     * Store a secret option.
     *
     * @param string $option    Option name.
     * @param string $plaintext Plaintext value.
     * @return true|WP_Error
     */
    public static function set_secret($option, $plaintext) {
        if ((string) $plaintext === '') {
            Adoology_Options::delete($option);
            return true;
        }

        $encrypted = self::encrypt((string) $plaintext, $option);
        if (is_wp_error($encrypted)) {
            return $encrypted;
        }

        if (!Adoology_Options::update($option, $encrypted) && (string) Adoology_Options::get($option, '') !== $encrypted) {
            return new WP_Error('adoology_secret_store_failed', __('Credential storage failed.', 'adoology-connector'));
        }
        return true;
    }

    /**
     * Read a secret, migrating plaintext left by older scaffold versions.
     *
     * @param string $option Option name.
     * @return string|WP_Error
     */
    public static function get_secret($option) {
        $stored = (string) Adoology_Options::get($option, '');
        if ($stored === '') {
            return '';
        }

        if (strpos($stored, self::PREFIX) !== 0) {
            $result = self::set_secret($option, $stored);
            if (is_wp_error($result)) {
                return $result;
            }
            return $stored;
        }

        return self::decrypt($stored, $option);
    }

    /**
     * Whether an encrypted value exists. Does not expose or decrypt it.
     *
     * @param string $option Option name.
     * @return bool
     */
    public static function has_secret($option) {
        return (string) Adoology_Options::get($option, '') !== '';
    }

    /**
     * Migrate one legacy plaintext option.
     *
     * @param string $option Option name.
     * @return true|WP_Error
     */
    public static function migrate_secret($option) {
        $value = self::get_secret($option);
        return is_wp_error($value) ? $value : true;
    }

    /**
     * Derive a site- and context-specific key from WordPress salts.
     *
     * @param string $context Storage context.
     * @return string
     */
    private static function key($context) {
        $material = wp_salt('auth') . '|' . wp_salt('secure_auth') . '|' . get_current_blog_id();
        return hash_hmac('sha256', 'adoology|' . $context, $material, true);
    }

    /**
     * Authenticated data binds ciphertext to this option and site.
     *
     * @param string $context Storage context.
     * @return string
     */
    private static function aad($context) {
        return 'adoology-connector|' . get_current_blog_id() . '|' . $context;
    }
}
