<?php
/**
 * Action Scheduler with WP-Cron fallback.
 *
 * @package Adoology_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

class Adoology_Scheduler {

    const GROUP = 'adoology-connector';

    /**
     * Schedule one unique action.
     *
     * @param int    $timestamp Unix timestamp.
     * @param string $hook      Hook.
     * @param array  $args      Hook arguments.
     * @return true|WP_Error
     */
    public static function schedule_single($timestamp, $hook, $args = array()) {
        $timestamp = max(time() + 1, (int) $timestamp);

        if (self::has_scheduled($hook, $args)) {
            return true;
        }

        if (function_exists('as_schedule_single_action') && did_action('action_scheduler_init')) {
            try {
                $action_id = as_schedule_single_action($timestamp, $hook, $args, self::GROUP, true);
                if ($action_id) {
                    return true;
                }
            } catch (Throwable $throwable) {
                Adoology_Logger::log('warning', 'Action Scheduler rejected an Adoology action.', array('hook' => $hook));
            }
        }

        $scheduled = wp_schedule_single_event($timestamp, $hook, $args, true);
        if (is_wp_error($scheduled)) {
            return $scheduled;
        }

        return $scheduled ? true : new WP_Error('adoology_schedule_failed', __('Could not schedule background work.', 'adoology-connector'));
    }

    /**
     * Test pending/running queues.
     *
     * @param string $hook Hook.
     * @param array  $args Hook arguments.
     * @return bool
     */
    public static function has_scheduled($hook, $args = array()) {
        if (function_exists('as_has_scheduled_action') && did_action('action_scheduler_init') && as_has_scheduled_action($hook, $args, self::GROUP)) {
            return true;
        }

        return wp_next_scheduled($hook, $args) !== false;
    }

    /**
     * Unschedule actions with exact arguments.
     *
     * @param string $hook Hook.
     * @param array  $args Hook arguments.
     */
    public static function unschedule($hook, $args = array()) {
        if (function_exists('as_unschedule_all_actions') && did_action('action_scheduler_init')) {
            as_unschedule_all_actions($hook, $args, self::GROUP);
        }

        wp_clear_scheduled_hook($hook, $args);
    }

    /**
     * Unschedule every action for a plugin-owned hook.
     *
     * @param string $hook Hook.
     */
    public static function unschedule_hook($hook) {
        if (function_exists('as_unschedule_all_actions') && did_action('action_scheduler_init')) {
            as_unschedule_all_actions($hook);
        }

        if (!function_exists('_get_cron_array')) {
            return;
        }

        $cron = _get_cron_array();
        if (!is_array($cron)) {
            return;
        }

        foreach ($cron as $timestamp => $hooks) {
            if (empty($hooks[$hook]) || !is_array($hooks[$hook])) {
                continue;
            }
            foreach ($hooks[$hook] as $event) {
                $args = isset($event['args']) && is_array($event['args']) ? $event['args'] : array();
                wp_unschedule_event((int) $timestamp, $hook, $args);
            }
        }
    }
}
