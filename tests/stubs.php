<?php

/**
 * Minimal WordPress and WooCommerce runtime stubs for unit tests.
 *
 * Functions here are plain fallbacks. Brain Monkey (Patchwork) can still
 * redefine them per test when behavior matters.
 */
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}
if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}
if (!defined('YEAR_IN_SECONDS')) {
    define('YEAR_IN_SECONDS', 31536000);
}

if (!defined('ADOOLOGY_VERSION')) {
    define('ADOOLOGY_VERSION', '0.1.0');
}
if (!defined('ADOOLOGY_PLUGIN_FILE')) {
    define('ADOOLOGY_PLUGIN_FILE', '/tmp/adoology-connector.php');
}
if (!defined('ADOOLOGY_PLUGIN_DIR')) {
    define('ADOOLOGY_PLUGIN_DIR', __DIR__ . '/../');
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT_K')) {
    define('OBJECT_K', 'OBJECT_K');
}
if (!defined('COOKIEPATH')) {
    define('COOKIEPATH', '/');
}
if (!defined('COOKIE_DOMAIN')) {
    define('COOKIE_DOMAIN', false);
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public $code = '';

        public $message = '';

        public $data = null;

        public function __construct($code = '', $message = '', $data = null)
        {
            $this->code = $code;
            $this->message = $message;
            $this->data = $data;
        }

        public function get_error_code()
        {
            return $this->code;
        }

        public function get_error_message()
        {
            return $this->message;
        }

        public function get_error_data()
        {
            return $this->data;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($value)
    {
        return $value instanceof WP_Error;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value, $flags = 0)
    {
        return json_encode($value, $flags);
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key)
    {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key));
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value)
    {
        return trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) $value)));
    }
}

if (!function_exists('absint')) {
    function absint($value)
    {
        return abs((int) $value);
    }
}
