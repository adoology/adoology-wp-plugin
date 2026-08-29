<?php
/**
 * Redacting WooCommerce logger wrapper.
 *
 * @package Adoology_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

class Adoology_Logger {

    const SOURCE = 'adoology-connector';

    /**
     * Write a sanitized log entry.
     *
     * @param string $level   Log level.
     * @param string $message Message.
     * @param array  $context Context.
     */
    public static function log($level, $message, $context = array()) {
        if (!function_exists('wc_get_logger')) {
            return;
        }

        $allowed = array('debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency');
        if (!in_array($level, $allowed, true)) {
            $level = 'info';
        }

        $safe_context = self::redact($context);
        $suffix       = empty($safe_context) ? '' : ' ' . wp_json_encode($safe_context);

        wc_get_logger()->log(
            $level,
            self::redact_string((string) $message) . $suffix,
            array('source' => self::SOURCE)
        );
    }

    /**
     * Redact nested values whose keys or content can contain credentials.
     *
     * @param mixed  $value Value.
     * @param string $key   Parent key.
     * @return mixed
     */
    public static function redact($value, $key = '') {
        if (preg_match('/(?:authorization|token|secret|signature|consumer_key|consumer_secret|api_key)/i', (string) $key)) {
            return '[redacted]';
        }

        if (is_array($value)) {
            $safe = array();
            foreach ($value as $child_key => $child_value) {
                $safe[$child_key] = self::redact($child_value, (string) $child_key);
            }
            return $safe;
        }

        if (is_object($value)) {
            return '[object]';
        }

        return is_string($value) ? self::redact_string($value) : $value;
    }

    /**
     * Redact known key formats and credential-bearing text.
     *
     * @param string $value Text.
     * @return string
     */
    public static function redact_string($value) {
        $value = preg_replace('/Bearer\s+[^\s,;]+/i', 'Bearer [redacted]', (string) $value);
        $value = preg_replace('/\b(?:dc|ck|cs)_[A-Za-z0-9._~-]+\b/', '[redacted]', (string) $value);
        $value = preg_replace('/("?(?:token|secret|signature|consumer_key|consumer_secret|authorization|api_key)"?\s*[:=]\s*)[^,;\s]+/i', '$1[redacted]', (string) $value);

        return (string) $value;
    }
}
