<?php

/**
 * Plugin database installation and upgrades.
 */

namespace Adoology;

if (!defined('ABSPATH')) {
    exit;
}

class Database
{
    const VERSION = '1.1.0';

    /**
     * Install or upgrade plugin tables.
     */
    public static function install()
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();
        $events = self::events_table();
        $incomplete = self::incomplete_table();

        dbDelta("CREATE TABLE {$events} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id char(26) NOT NULL,
            event_name varchar(120) NOT NULL,
            anonymous_id varchar(128) NOT NULL,
            payload longtext NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            attempts smallint(5) unsigned NOT NULL DEFAULT 0,
            available_at datetime NOT NULL,
            lease_token char(36) NULL,
            lease_expires_at datetime NULL,
            last_error text NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            sent_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_id (event_id),
            KEY delivery (status,available_at),
            KEY lease (lease_token,lease_expires_at),
            KEY event_name (event_name,created_at)
        ) {$charset_collate};");

        dbDelta("CREATE TABLE {$incomplete} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            checkout_id varchar(36) NOT NULL,
            session_id varchar(128) NOT NULL,
            flow varchar(20) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'started',
            product_id bigint(20) unsigned NOT NULL DEFAULT 0,
            variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            quantity int(10) unsigned NOT NULL DEFAULT 1,
            value_minor bigint(20) unsigned NOT NULL DEFAULT 0,
            currency char(3) NOT NULL DEFAULT '',
            customer_data longtext NULL,
            landing_page text NULL,
            form_stage varchar(40) NOT NULL DEFAULT 'started',
            risk_score smallint(5) unsigned NOT NULL DEFAULT 0,
            order_id bigint(20) unsigned NOT NULL DEFAULT 0,
            last_activity_at datetime NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY checkout_id (checkout_id),
            KEY lifecycle (status,last_activity_at),
            KEY order_id (order_id),
            KEY expires_at (expires_at)
        ) {$charset_collate};");

        Options::update('adoology_db_version', self::VERSION);
    }

    /**
     * Upgrade when source schema changes.
     */
    public static function maybe_upgrade()
    {
        if ((string) Options::get('adoology_db_version', '') !== self::VERSION) {
            self::install();
        }
    }

    /**
     * Event outbox table.
     *
     * @return string
     */
    public static function events_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'adoology_events';
    }

    /**
     * Incomplete checkout table.
     *
     * @return string
     */
    public static function incomplete_table()
    {
        global $wpdb;

        return $wpdb->prefix . 'adoology_incomplete_orders';
    }
}
