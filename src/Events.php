<?php

/**
 * Durable Adoology event outbox.
 */

namespace Adoology;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

class Events
{
    const PROCESS_HOOK = 'adoology_process_events';

    const CLEANUP_HOOK = 'adoology_cleanup_events';

    const MAX_ATTEMPTS = 8;

    const CONTINUATION_OPTION = 'adoology_events_continuation_state';

    /**
     * Register queue callbacks.
     */
    public static function register()
    {
        add_action(self::PROCESS_HOOK, [self::class, 'process'], 10, 2);
        add_action(self::CLEANUP_HOOK, [self::class, 'cleanup']);
    }

    /**
     * Enqueue one generic Adoology event.
     *
     * @param  string  $name  Event name.
     * @param  string  $anonymous_id  Anonymous browser identifier.
     * @param  string  $session_id  Checkout/session identifier.
     * @param  array  $properties  Event properties.
     * @param  array  $context  Request context.
     * @return string|WP_Error Event ULID or error.
     */
    public static function enqueue($name, $anonymous_id, $session_id, $properties = [], $context = [])
    {
        global $wpdb;

        $name = sanitize_key(str_replace('.', '_', (string) $name));
        $name = str_replace('_', '.', $name);
        $anonymous_id = substr(sanitize_text_field((string) $anonymous_id), 0, 128);
        $session_id = substr(sanitize_text_field((string) $session_id), 0, 128);
        $connection_id = Connection::connection_id();
        if ($name === '' || $anonymous_id === '') {
            return new WP_Error('adoology_invalid_event', __('Event name and anonymous identifier are required.', 'adoology-connector'));
        }

        $event_id = self::ulid();
        $event = [
            'id' => $event_id,
            'name' => substr($name, 0, 120),
            'anonymous_id' => $anonymous_id,
            'session_id' => $session_id !== '' ? $session_id : null,
            'channel_connection_id' => $connection_id !== '' ? $connection_id : null,
            'properties' => self::sanitize_value($properties, 0),
            'context' => self::sanitize_value($context, 0),
            'source' => 'api',
            'occurred_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z'),
        ];
        $json = wp_json_encode($event);
        if (!is_string($json) || strlen($json) > 65535) {
            return new WP_Error('adoology_event_too_large', __('Event payload is too large.', 'adoology-connector'));
        }

        $encrypted = Crypto::encrypt($json, 'adoology_event_' . $event_id);
        if (is_wp_error($encrypted)) {
            return $encrypted;
        }

        $now = gmdate('Y-m-d H:i:s');
        $inserted = $wpdb->insert(
            Database::events_table(),
            [
                'event_id' => $event_id,
                'event_name' => $event['name'],
                'anonymous_id' => $anonymous_id,
                'payload' => $encrypted,
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        if (!$inserted) {
            return new WP_Error('adoology_event_store_failed', __('Could not queue the Adoology event.', 'adoology-connector'));
        }

        self::schedule_processing();

        return $event_id;
    }

    /**
     * Deliver one batch to Adoology.
     */
    public static function process($kind = null, $continuation_token = '')
    {
        try {
            self::process_batch($continuation_token);
        } finally {
            self::release_continuation($continuation_token);
        }
    }

    private static function process_batch($continuation_token)
    {
        global $wpdb;

        if (!Connection::is_connected() || !Crypto::has_secret('adoology_api_token')) {
            return;
        }

        $table = Database::events_table();
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET status = 'retrying', lease_token = NULL, lease_expires_at = NULL, available_at = %s, updated_at = %s WHERE status = 'processing' AND (lease_expires_at IS NULL OR lease_expires_at < %s)",
            $now,
            $now,
            $now
        ));
        $candidate_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$table} WHERE status IN ('pending','retrying') AND available_at <= %s ORDER BY id ASC LIMIT 25",
            $now
        ));
        if (empty($candidate_ids)) {
            return;
        }

        $lease_token = wp_generate_uuid4();
        $placeholders = implode(',', array_fill(0, count($candidate_ids), '%d'));
        $claim_query = $wpdb->prepare(
            "UPDATE {$table} SET status = 'processing', lease_token = %s, lease_expires_at = %s, updated_at = %s WHERE id IN ({$placeholders}) AND status IN ('pending','retrying') AND available_at <= %s",
            array_merge([$lease_token, gmdate('Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS), $now], array_map('intval', $candidate_ids), [$now])
        );
        $wpdb->query($claim_query);
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, event_id, payload, attempts FROM {$table} WHERE lease_token = %s AND status = 'processing' ORDER BY id ASC",
            $lease_token
        ), ARRAY_A);
        if (empty($rows)) {
            return;
        }

        $events = [];
        $ids = [];
        foreach ($rows as $row) {
            $decrypted = Crypto::decrypt((string) $row['payload'], 'adoology_event_' . $row['event_id']);
            $decoded = is_wp_error($decrypted) ? null : json_decode($decrypted, true);
            if (!is_array($decoded)) {
                self::mark_failed((int) $row['id'], (int) $row['attempts'], 'Stored event cannot be decrypted.', $lease_token);

                continue;
            }
            $events[] = $decoded;
            $ids[] = (int) $row['id'];
        }
        if (empty($events)) {
            return;
        }

        $result = ApiClient::ingest_events($events, 'ado-events-' . hash('sha256', implode('-', array_column($events, 'id'))));
        if (is_wp_error($result)) {
            foreach ($rows as $row) {
                if (in_array((int) $row['id'], $ids, true)) {
                    self::mark_failed((int) $row['id'], (int) $row['attempts'], $result->get_error_message(), $lease_token);
                }
            }
            self::schedule_processing(time() + 60, true, $continuation_token);

            return;
        }

        $sent_placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $query = $wpdb->prepare(
            "UPDATE {$table} SET status = 'sent', sent_at = %s, updated_at = %s, last_error = NULL, lease_token = NULL, lease_expires_at = NULL WHERE id IN ({$sent_placeholders}) AND status = 'processing' AND lease_token = %s",
            array_merge([$now, $now], $ids, [$lease_token])
        );
        $wpdb->query($query);
        self::schedule_processing(time() + 1, true, $continuation_token);
    }

    /**
     * Purge sent events and redact old failed payloads.
     */
    public static function cleanup()
    {
        global $wpdb;

        $table = Database::events_table();
        $retention = min(90, max(1, (int) Options::get('adoology_incomplete_expire_days', 7)));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE status IN ('pending','retrying','processing','failed') AND created_at < %s",
            gmdate('Y-m-d H:i:s', time() - $retention * DAY_IN_SECONDS)
        ));
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE status = 'sent' AND sent_at < %s",
            gmdate('Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS)
        ));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$table} SET payload = '', updated_at = %s WHERE status = 'failed' AND payload <> '' AND updated_at < %s",
            gmdate('Y-m-d H:i:s'),
            gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS)
        ));
    }

    /**
     * Queue processing through Action Scheduler or WP-Cron.
     *
     * @param  int|null  $timestamp  Optional run timestamp.
     * @param  bool  $continuation  Schedule behind the currently running action.
     * @param  string  $current_token  Current continuation owner.
     */
    public static function schedule_processing($timestamp = null, $continuation = false, $current_token = '')
    {
        $run_at = $timestamp ?: time() + 1;
        if (!$continuation) {
            Scheduler::schedule_single($run_at, self::PROCESS_HOOK, ['async']);

            return;
        }

        $token = self::claim_continuation($current_token);
        if ($token === '') {
            return;
        }
        $scheduled = Scheduler::schedule_single($run_at, self::PROCESS_HOOK, ['continuation', $token]);
        if (is_wp_error($scheduled)) {
            self::release_continuation($token);
        }
    }

    private static function claim_continuation($current_token)
    {
        $state = Options::get(self::CONTINUATION_OPTION, []);
        if ($current_token !== '') {
            if (!is_array($state) || !isset($state['token']) || !hash_equals((string) $state['token'], (string) $current_token)) {
                return '';
            }
        } else {
            if (is_array($state) && isset($state['created_at']) && time() - (int) $state['created_at'] >= 10 * MINUTE_IN_SECONDS) {
                Options::delete(self::CONTINUATION_OPTION);
            }
            $token = wp_generate_uuid4();
            if (!add_option(self::CONTINUATION_OPTION, ['token' => $token, 'created_at' => time()], '', false)) {
                return '';
            }

            return $token;
        }

        $token = wp_generate_uuid4();
        Options::update(self::CONTINUATION_OPTION, ['token' => $token, 'created_at' => time()]);

        return $token;
    }

    private static function release_continuation($token)
    {
        $state = Options::get(self::CONTINUATION_OPTION, []);
        if ($token !== '' && is_array($state) && isset($state['token']) && hash_equals((string) $state['token'], (string) $token)) {
            Options::delete(self::CONTINUATION_OPTION);
        }
    }

    /**
     * Mark a failed attempt and apply bounded exponential backoff.
     *
     * @param  int  $id  Row ID.
     * @param  int  $attempts  Prior attempts.
     * @param  string  $error  Safe error.
     * @param  string  $lease_token  Worker lease.
     */
    private static function mark_failed($id, $attempts, $error, $lease_token)
    {
        global $wpdb;

        $attempts++;
        $terminal = $attempts >= self::MAX_ATTEMPTS;
        $delay = min(DAY_IN_SECONDS, (int) 2 ** min($attempts, 10) * 60);
        $wpdb->update(
            Database::events_table(),
            [
                'status' => $terminal ? 'failed' : 'retrying',
                'attempts' => $attempts,
                'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
                'last_error' => substr(Logger::redact_string(sanitize_text_field($error)), 0, 1000),
                'updated_at' => gmdate('Y-m-d H:i:s'),
                'lease_token' => null,
                'lease_expires_at' => null,
            ],
            ['id' => $id, 'status' => 'processing', 'lease_token' => $lease_token],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s'],
            ['%d', '%s', '%s']
        );
    }

    /**
     * Recursively sanitize event data and cap depth/size.
     *
     * @param  mixed  $value  Value.
     * @param  int  $depth  Current depth.
     * @return mixed
     */
    private static function sanitize_value($value, $depth)
    {
        if ($depth > 4) {
            return null;
        }
        if (is_array($value)) {
            $safe = [];
            foreach (array_slice($value, 0, 50, true) as $key => $child) {
                $safe[sanitize_key((string) $key)] = self::sanitize_value($child, $depth + 1);
            }

            return $safe;
        }
        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return substr(sanitize_text_field((string) $value), 0, 1000);
    }

    /**
     * Generate a monotonic-enough ULID for API deduplication.
     *
     * @return string
     */
    private static function ulid()
    {
        $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $time = (int) floor(microtime(true) * 1000);
        $output = '';
        for ($index = 0; $index < 10; $index++) {
            $output = $alphabet[$time % 32] . $output;
            $time = (int) floor($time / 32);
        }
        try {
            $bytes = random_bytes(10);
        } catch (Exception $exception) {
            $bytes = hash('sha256', wp_generate_uuid4() . microtime(true), true);
        }
        $bits = '';
        for ($index = 0; $index < 10; $index++) {
            $bits .= str_pad(decbin(ord($bytes[$index])), 8, '0', STR_PAD_LEFT);
        }
        for ($index = 0; $index < 16; $index++) {
            $output .= $alphabet[bindec(substr($bits, $index * 5, 5))];
        }

        return $output;
    }
}
