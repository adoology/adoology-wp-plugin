<?php

/**
 * Hardened Adoology API client.
 */

namespace Adoology;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class ApiClient
{
    const MAX_ATTEMPTS = 3;

    /**
     * Configured API base, excluding /v1.
     *
     * @return string
     */
    public static function base_url()
    {
        $url = self::validate_base_url((string) Options::get('adoology_api_base_url', 'https://api.adoology.com'));

        return is_wp_error($url) ? '' : $url;
    }

    /**
     * Validate and normalize an HTTPS API base URL.
     *
     * @param  string  $url  Candidate URL.
     * @return string|WP_Error
     */
    public static function validate_base_url($url)
    {
        $url = untrailingslashit(trim((string) $url));
        if ($url === '' || !wp_http_validate_url($url)) {
            return new WP_Error('adoology_invalid_api_url', __('Enter a valid public HTTPS Adoology API base URL.', 'adoology-connector'));
        }

        $parts = wp_parse_url($url);
        if (!is_array($parts) || strtolower((string) $parts['scheme']) !== 'https' || empty($parts['host'])) {
            return new WP_Error('adoology_invalid_api_url', __('Adoology API URL must use HTTPS.', 'adoology-connector'));
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return new WP_Error('adoology_invalid_api_url', __('Adoology API URL cannot contain credentials, a query, or a fragment.', 'adoology-connector'));
        }

        $path = isset($parts['path']) ? strtolower(untrailingslashit((string) $parts['path'])) : '';
        if (preg_match('#/(?:api|api/v1|v1)$#', $path)) {
            return new WP_Error('adoology_versioned_api_url', __('Enter the API base URL without /api or /v1.', 'adoology-connector'));
        }

        return esc_url_raw($url, ['https']);
    }

    /**
     * Validate workspace token syntax without exposing it.
     *
     * @param  mixed  $token  Token.
     * @return bool
     */
    public static function is_valid_token($token)
    {
        return is_string($token) && strlen($token) <= 512 && (bool) preg_match('/^dc_[A-Za-z0-9._~-]{8,}$/D', $token);
    }

    /**
     * Read encrypted workspace token.
     *
     * @return string|WP_Error
     */
    public static function token()
    {
        return Crypto::get_secret('adoology_api_token');
    }

    /**
     * Generate an opaque idempotency key.
     *
     * @return string
     */
    public static function new_idempotency_key()
    {
        return 'ado-' . wp_generate_uuid4();
    }

    /**
     * Send an authenticated request to {base}/v1.
     *
     * @param  string  $method  HTTP method.
     * @param  string  $path  Path below /v1.
     * @param  array|null  $body  JSON body.
     * @param  string  $idempotency_key  Stable key for a logical write.
     * @param  int  $max_attempts  Maximum request attempts.
     * @param  int  $timeout  Timeout per attempt in seconds.
     * @return array|WP_Error
     */
    public static function request($method, $path, $body = null, $idempotency_key = '', $max_attempts = self::MAX_ATTEMPTS, $timeout = 20)
    {
        $method = strtoupper((string) $method);
        $writes = ['POST', 'PATCH', 'DELETE'];
        $max_attempts = max(1, min(self::MAX_ATTEMPTS, (int) $max_attempts));
        $timeout = max(1, min(20, (int) $timeout));

        if (!in_array($method, ['GET', 'POST', 'PATCH', 'DELETE'], true)) {
            return new WP_Error('adoology_invalid_method', __('Unsupported Adoology API method.', 'adoology-connector'));
        }

        if (!is_string($path) || !preg_match('#^/[A-Za-z0-9_/%.-]*$#D', $path) || strpos($path, '..') !== false) {
            return new WP_Error('adoology_invalid_path', __('Invalid Adoology API path.', 'adoology-connector'));
        }

        $base_url = self::base_url();
        if ($base_url === '') {
            return new WP_Error('adoology_invalid_api_url', __('Configured Adoology API URL is invalid.', 'adoology-connector'));
        }

        $token = self::token();
        if (is_wp_error($token)) {
            return $token;
        }
        if (!self::is_valid_token($token)) {
            return new WP_Error('adoology_no_token', __('Enter a valid Adoology workspace API key beginning with dc_.', 'adoology-connector'));
        }

        $url = $base_url . '/v1' . $path;
        $url_path = (string) wp_parse_url($url, PHP_URL_PATH);
        if (strpos($url_path, '/api/v1') !== false || !wp_http_validate_url($url)) {
            return new WP_Error('adoology_invalid_api_url', __('Refusing an unsafe or legacy Adoology API URL.', 'adoology-connector'));
        }

        if (in_array($method, $writes, true) && $idempotency_key === '') {
            $idempotency_key = self::new_idempotency_key();
        }
        if ($idempotency_key !== '' && !preg_match('/^[A-Za-z0-9._:-]{8,200}$/D', $idempotency_key)) {
            return new WP_Error('adoology_invalid_idempotency_key', __('Invalid idempotency key.', 'adoology-connector'));
        }

        $plugin_version = defined('ADOOLOGY_VERSION') ? ADOOLOGY_VERSION : 'unknown';
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
        if (in_array($method, $writes, true)) {
            $headers['Idempotency-Key'] = $idempotency_key;
        }

        $encoded_body = null;
        if ($body !== null) {
            $encoded_body = wp_json_encode($body);
            if (!is_string($encoded_body)) {
                return new WP_Error('adoology_json_encode_failed', __('Could not encode the Adoology API request.', 'adoology-connector'));
            }
        }

        $last_response = null;
        for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
            $response = wp_safe_remote_request($url, [
                'method' => $method,
                'timeout' => $timeout,
                'redirection' => 0,
                'reject_unsafe_urls' => true,
                'httpversion' => '1.1',
                'user-agent' => 'Adoology-Connector/' . $plugin_version . '; ' . home_url('/'),
                'headers' => $headers,
                'body' => $encoded_body,
            ]);

            $last_response = $response;
            if (!self::should_retry($response) || $attempt === $max_attempts) {
                break;
            }

            Logger::log('warning', 'Retrying Adoology API request.', [
                'method' => $method,
                'path' => $path,
                'attempt' => $attempt + 1,
                'status' => is_wp_error($response) ? 'transport' : (int) wp_remote_retrieve_response_code($response),
            ]);
            usleep(self::retry_delay_microseconds($attempt, $response));
        }

        if (is_wp_error($last_response)) {
            Logger::log('error', 'Adoology API transport failure.', ['method' => $method, 'path' => $path]);

            return new WP_Error('adoology_api_transport', __('Could not reach the Adoology API.', 'adoology-connector'));
        }

        $status = (int) wp_remote_retrieve_response_code($last_response);
        $response_raw = (string) wp_remote_retrieve_body($last_response);
        $content_type = strtolower((string) wp_remote_retrieve_header($last_response, 'content-type'));
        $is_json = strpos($content_type, '/json') !== false || strpos($content_type, '+json') !== false;
        $decoded = null;

        if ($response_raw !== '' && $is_json) {
            $decoded = json_decode($response_raw, true);
        }

        if ($status < 200 || $status >= 300) {
            $message = self::response_error_message($decoded, $status);
            Logger::log('error', 'Adoology API rejected a request.', ['method' => $method, 'path' => $path, 'status' => $status]);

            return new WP_Error('adoology_api_http', $message, ['status' => $status]);
        }

        if ($status === 204 || $response_raw === '') {
            return [];
        }

        if (!$is_json || !is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            Logger::log('error', 'Adoology API returned invalid JSON.', ['method' => $method, 'path' => $path, 'status' => $status]);

            return new WP_Error('adoology_api_invalid_json', __('Adoology API returned an invalid JSON response.', 'adoology-connector'), ['status' => $status]);
        }

        return $decoded;
    }

    /**
     * Read one channel connection.
     *
     * @param  string  $connection_id  Connection ID.
     * @return array|WP_Error
     */
    public static function get_connection($connection_id)
    {
        return self::request('GET', '/channel-connections/' . rawurlencode((string) $connection_id));
    }

    /**
     * Create a channel connection.
     *
     * @param  array  $payload  Connection payload.
     * @param  string  $idempotency_key  Stable key.
     * @return array|WP_Error
     */
    public static function create_connection($payload, $idempotency_key)
    {
        return self::request('POST', '/channel-connections', $payload, $idempotency_key);
    }

    /**
     * Update connection settings or credentials.
     *
     * @param  string  $connection_id  Connection ID.
     * @param  array  $payload  Update payload.
     * @param  string  $idempotency_key  Stable key.
     * @return array|WP_Error
     */
    public static function patch_connection($connection_id, $payload, $idempotency_key = '')
    {
        return self::request('PATCH', '/channel-connections/' . rawurlencode((string) $connection_id), $payload, $idempotency_key);
    }

    /**
     * Verify Woo credentials and webhooks.
     *
     * @param  string  $connection_id  Connection ID.
     * @param  array  $webhooks  Webhook descriptors.
     * @param  string  $idempotency_key  Stable key.
     * @return array|WP_Error
     */
    public static function verify_connection($connection_id, $webhooks, $idempotency_key)
    {
        return self::request(
            'POST',
            '/channel-connections/' . rawurlencode((string) $connection_id) . '/verify',
            ['webhooks' => $webhooks],
            $idempotency_key
        );
    }

    /**
     * Delete a channel connection.
     *
     * @param  string  $connection_id  Connection ID.
     * @param  string  $idempotency_key  Stable key.
     * @param  bool  $single_attempt  Use one bounded attempt for teardown.
     * @return array|WP_Error
     */
    public static function delete_connection($connection_id, $idempotency_key, $single_attempt = false)
    {
        return self::request(
            'DELETE',
            '/channel-connections/' . rawurlencode((string) $connection_id),
            null,
            $idempotency_key,
            $single_attempt ? 1 : self::MAX_ATTEMPTS,
            $single_attempt ? 5 : 20
        );
    }

    /**
     * Ingest generic storefront events.
     *
     * @param  array  $events  Event batch.
     * @param  string  $idempotency_key  Stable batch key.
     * @return array|WP_Error
     */
    public static function ingest_events($events, $idempotency_key)
    {
        return self::request('POST', '/events', ['events' => array_values($events)], $idempotency_key, 1, 10);
    }

    /**
     * Start a manual full product synchronization.
     *
     * @param  string  $connection_id  Connection ID.
     * @return array|WP_Error
     */
    public static function start_product_sync($connection_id)
    {
        return self::request(
            'POST',
            '/channel-connections/' . rawurlencode((string) $connection_id) . '/sync-products',
            null,
            self::new_idempotency_key()
        );
    }

    /**
     * Read latest synchronization runs.
     *
     * @param  string  $connection_id  Connection ID.
     * @return array|WP_Error
     */
    public static function get_sync_runs($connection_id)
    {
        return self::request('GET', '/channel-connections/' . rawurlencode((string) $connection_id) . '/sync-runs');
    }

    /**
     * Extract an HTTP status from a client error.
     *
     * @param  mixed  $error  Error.
     * @return int
     */
    public static function error_status($error)
    {
        if (!is_wp_error($error)) {
            return 0;
        }
        $data = $error->get_error_data();

        return is_array($data) && isset($data['status']) ? (int) $data['status'] : 0;
    }

    /**
     * Determine whether an attempt is safely retryable.
     *
     * @param  array|WP_Error  $response  Response.
     * @return bool
     */
    private static function should_retry($response)
    {
        if (is_wp_error($response)) {
            return true;
        }

        $status = (int) wp_remote_retrieve_response_code($response);

        return $status === 429 || $status >= 500;
    }

    /**
     * Bounded exponential delay, honoring short Retry-After values.
     *
     * @param  int  $attempt  Attempt number.
     * @param  array|WP_Error  $response  Response.
     * @return int Microseconds.
     */
    private static function retry_delay_microseconds($attempt, $response)
    {
        $seconds = min(2, (int) 2 ** max(0, $attempt - 1));
        if (!is_wp_error($response)) {
            $retry_after = wp_remote_retrieve_header($response, 'retry-after');
            if (is_numeric($retry_after)) {
                $seconds = min(3, max($seconds, (int) $retry_after));
            }
        }

        return max(100000, $seconds * 1000000);
    }

    /**
     * Extract only a safe server message.
     *
     * @param  mixed  $decoded  Decoded body.
     * @param  int  $status  HTTP status.
     * @return string
     */
    private static function response_error_message($decoded, $status)
    {
        $message = '';
        if (is_array($decoded)) {
            if (isset($decoded['message']) && is_string($decoded['message'])) {
                $message = $decoded['message'];
            } elseif (isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                $message = $decoded['error']['message'];
            } elseif (isset($decoded['data']['message']) && is_string($decoded['data']['message'])) {
                $message = $decoded['data']['message'];
            }
        }

        if ($message === '') {
            $message = sprintf(
                /* translators: %d: HTTP response status. */
                __('Adoology API request failed with HTTP %d.', 'adoology-connector'),
                (int) $status
            );
        }

        return Logger::redact_string(sanitize_text_field($message));
    }
}
