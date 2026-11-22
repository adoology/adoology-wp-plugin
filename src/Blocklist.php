<?php

/**
 * Local hashed copy of the workspace blocklist and checkout matching.
 *
 * Values are compared as sha256 hashes of the same normalized form the
 * Adoology API stores: E.164 phone, canonical lower-case IP, lower-case
 * device ID. Raw phones and IPs from other stores never reach this site.
 */

namespace Adoology;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Blocklist
{
    public const OPTION = 'adoology_blocklist';

    public const REFRESH_HOOK = 'adoology_blocklist_refresh';

    /** A list older than this still applies, but the admin is warned. */
    public const STALE_AFTER = DAY_IN_SECONDS;

    /**
     * Register refresh hook.
     */
    public static function register()
    {
        add_action(self::REFRESH_HOOK, [self::class, 'refresh']);
        add_action('admin_notices', [self::class, 'stale_notice']);
    }

    /**
     * Whether blocklist checks run at checkout.
     *
     * @return bool
     */
    public static function enabled()
    {
        return Options::get('adoology_blocklist_enabled', 'yes') === 'yes';
    }

    /**
     * Download the workspace blocklist and replace the local copy.
     *
     * @return true|WP_Error
     */
    public static function refresh()
    {
        if (!Connection::is_connected()) {
            return true;
        }
        $response = ApiClient::request('GET', '/blocklist/store-copy', null, '', 1, 10);
        if (is_wp_error($response)) {
            Logger::log('warning', 'Adoology blocklist download failed.', ['error' => $response->get_error_code()]);

            return $response;
        }
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $version = sanitize_text_field((string) ($data['version'] ?? ''));
        $current = self::stored();
        if ($version !== '' && $version === ($current['version'] ?? '')) {
            $current['fetched_at'] = time();
            Options::update(self::OPTION, $current);

            return true;
        }

        $index = ['ip' => [], 'phone' => [], 'device' => []];
        foreach ((array) ($data['entries'] ?? []) as $entry) {
            $type = (string) ($entry['type'] ?? '');
            $hash = strtolower((string) ($entry['hash'] ?? ''));
            $id = sanitize_text_field((string) ($entry['id'] ?? ''));
            if (!isset($index[$type]) || !preg_match('/^[a-f0-9]{64}$/D', $hash) || $id === '') {
                continue;
            }
            $expires = isset($entry['expires_at']) && $entry['expires_at'] !== null ? strtotime((string) $entry['expires_at']) : 0;
            $index[$type][$hash] = [$id, $expires ? (int) $expires : 0];
        }

        Options::update(self::OPTION, [
            'version' => $version,
            'fetched_at' => time(),
            'index' => $index,
        ]);

        return true;
    }

    /**
     * Download soon in the background (after a signed change ping).
     */
    public static function schedule_refresh()
    {
        return Scheduler::schedule_single(time() + 1, self::REFRESH_HOOK);
    }

    /**
     * Active entries matching this checkout. An empty result also means
     * the list was never downloaded (fail open).
     *
     * @param  string  $ip  Client IP.
     * @param  string  $phone_e164  E.164 phone.
     * @param  string  $device_id  Storefront device ID.
     * @return array<int, array{id: string, type: string}>
     */
    public static function match($ip, $phone_e164, $device_id)
    {
        $index = self::stored()['index'] ?? null;
        if (!is_array($index)) {
            return [];
        }
        $now = time();
        $matches = [];
        foreach (['ip' => self::canonical_ip($ip), 'phone' => (string) $phone_e164, 'device' => strtolower((string) $device_id)] as $type => $value) {
            if ($value === '') {
                continue;
            }
            $entry = $index[$type][hash('sha256', $value)] ?? null;
            if (is_array($entry) && ((int) $entry[1] === 0 || (int) $entry[1] > $now)) {
                $matches[] = ['id' => (string) $entry[0], 'type' => $type];
            }
        }

        return $matches;
    }

    /**
     * Normalize a billing phone to E.164, matching the Adoology API:
     * "01711-311160", "8801711311160" and "+880 1711 311160" all become
     * "+8801711311160" for a Bangladesh store.
     *
     * @param  string  $raw  Raw phone.
     * @param  string  $country  Billing country (ISO 3166-1 alpha-2).
     * @param  string|null  $calling_code  Calling code digits; derived from WooCommerce when null.
     * @return string
     */
    public static function normalize_phone($raw, $country = '', $calling_code = null)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return '';
        }
        if (strpos($raw, '+') === 0) {
            return '+' . $digits;
        }
        if (strpos($digits, '00') === 0) {
            return '+' . substr($digits, 2);
        }
        $calling = $calling_code !== null ? preg_replace('/\D+/', '', (string) $calling_code) : self::calling_code($country);
        if ($calling === '') {
            return '';
        }
        if (strpos($digits, (string) $calling) === 0 && strlen($digits) > strlen($calling) + 6) {
            return '+' . $digits;
        }

        return '+' . $calling . ltrim($digits, '0');
    }

    /**
     * Client IP honouring the configured proxy header.
     *
     * @return string
     */
    public static function client_ip()
    {
        $source = (string) Options::get('adoology_ip_source', 'remote_addr');
        $candidates = [];
        if ($source === 'cf-connecting-ip') {
            $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        } elseif ($source === 'x-forwarded-for') {
            $forwarded = explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
            $candidates[] = $forwarded[0] ?? '';
        }
        $candidates[] = $_SERVER['REMOTE_ADDR'] ?? '';
        foreach ($candidates as $candidate) {
            $ip = self::canonical_ip(sanitize_text_field(wp_unslash((string) $candidate)));
            if ($ip !== '') {
                return $ip;
            }
        }

        return '';
    }

    /**
     * Canonical lower-case text form of an IP, or empty when invalid.
     *
     * @param  string  $ip  IP.
     * @return string
     */
    public static function canonical_ip($ip)
    {
        $ip = trim((string) $ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return '';
        }
        $packed = inet_pton($ip);
        $text = $packed === false ? false : inet_ntop($packed);

        return $text === false ? '' : strtolower($text);
    }

    /**
     * Report a stopped attempt so Adoology can count it against the blocks.
     *
     * @param  array  $matches  Matches from match().
     */
    public static function report_hit($matches)
    {
        if (empty($matches)) {
            return;
        }
        $identity = IncompleteOrders::identity();
        Events::enqueue('blocklist.matched', $identity['anonymous_id'], $identity['session_id'], [
            'block_ids' => array_values(array_unique(array_column($matches, 'id'))),
            'types' => array_values(array_unique(array_column($matches, 'type'))),
        ], IncompleteOrders::request_context());
    }

    /**
     * Local copy summary for the settings page.
     *
     * @return array{version: string, fetched_at: int, count: int}
     */
    public static function summary()
    {
        $stored = self::stored();
        $count = 0;
        foreach ((array) ($stored['index'] ?? []) as $entries) {
            $count += is_array($entries) ? count($entries) : 0;
        }

        return [
            'version' => (string) ($stored['version'] ?? ''),
            'fetched_at' => (int) ($stored['fetched_at'] ?? 0),
            'count' => $count,
        ];
    }

    /**
     * Warn admins when the list has not refreshed for a day.
     */
    public static function stale_notice()
    {
        if (!self::enabled() || !Connection::is_connected() || !current_user_can('manage_woocommerce')) {
            return;
        }
        $fetched_at = self::summary()['fetched_at'];
        if ($fetched_at > 0 && time() - $fetched_at < self::STALE_AFTER) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__('Adoology could not refresh the blocklist for over a day. Checkout still uses the last copy. Check the connection on Adoology → Connection.', 'adoology-for-woocommerce') . '</p></div>';
    }

    /**
     * @return array
     */
    private static function stored()
    {
        $stored = Options::get(self::OPTION, []);

        return is_array($stored) ? $stored : [];
    }

    /**
     * Country calling code digits for the billing or store country.
     *
     * @param  string  $country  Country code.
     * @return string
     */
    private static function calling_code($country)
    {
        if (!function_exists('WC') || !WC()->countries) {
            return '';
        }
        $country = strtoupper((string) $country);
        if ($country === '') {
            $country = (string) WC()->countries->get_base_country();
        }
        $code = WC()->countries->get_country_calling_code($country);
        $code = is_array($code) ? (string) reset($code) : (string) $code;

        return preg_replace('/\D+/', '', $code);
    }
}
