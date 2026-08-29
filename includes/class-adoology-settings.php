<?php
/**
 * Adoology administration page.
 *
 * @package Adoology_Connector
 */

if (!defined('ABSPATH')) {
    exit;
}

class Adoology_Settings {

    /**
     * @var Adoology_Settings|null
     */
    private static $instance = null;

    /**
     * @return Adoology_Settings
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_post_adoology_connect', array($this, 'handle_connect'));
        add_action('admin_post_adoology_test_connection', array($this, 'handle_test_connection'));
        add_action('admin_post_adoology_disconnect', array($this, 'handle_disconnect'));
        add_action('admin_post_adoology_sync_products', array($this, 'handle_sync_products'));
        add_action('admin_post_adoology_retry_events', array($this, 'handle_retry_events'));
        add_filter('option_page_capability_adoology_settings', array($this, 'settings_capability'));
        add_filter('option_page_capability_adoology_features', array($this, 'settings_capability'));
    }

    /**
     * Add WooCommerce submenu.
     */
    public function add_menu() {
        add_menu_page(
            __('Adoology', 'adoology-connector'),
            __('Adoology', 'adoology-connector'),
            'manage_woocommerce',
            'adoology',
            array($this, 'render_dashboard'),
            'dashicons-shield-alt',
            56
        );
        add_submenu_page('adoology', __('Adoology Dashboard', 'adoology-connector'), __('Dashboard', 'adoology-connector'), 'manage_woocommerce', 'adoology', array($this, 'render_dashboard'));
        add_submenu_page('adoology', __('Adoology Connection', 'adoology-connector'), __('Connection', 'adoology-connector'), 'manage_woocommerce', 'adoology-connection', array($this, 'render_settings_page'));
        add_submenu_page('adoology', __('Adoology Sync', 'adoology-connector'), __('Sync', 'adoology-connector'), 'manage_woocommerce', 'adoology-sync', array($this, 'render_sync'));
        add_submenu_page('adoology', __('Incomplete Orders', 'adoology-connector'), __('Incomplete Orders', 'adoology-connector'), 'manage_woocommerce', 'adoology-incomplete', array($this, 'render_incomplete_orders'));
        add_submenu_page('adoology', __('Fraud Protection', 'adoology-connector'), __('Fraud Protection', 'adoology-connector'), 'manage_woocommerce', 'adoology-fraud', array($this, 'render_fraud'));
        add_submenu_page('adoology', __('Adoology Order Form', 'adoology-connector'), __('Order Form', 'adoology-connector'), 'manage_woocommerce', 'adoology-order-form', array($this, 'render_order_form'));
        add_submenu_page('adoology', __('Adoology Settings', 'adoology-connector'), __('Settings', 'adoology-connector'), 'manage_woocommerce', 'adoology-settings', array($this, 'render_feature_settings'));
        add_submenu_page('adoology', __('Adoology Logs', 'adoology-connector'), __('Logs', 'adoology-connector'), 'manage_woocommerce', 'adoology-logs', array($this, 'render_logs'));
    }

    /**
     * Register connection settings.
     */
    public function register_settings() {
        register_setting('adoology_settings', 'adoology_api_base_url', array(
            'type'              => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_base_url'),
            'default'           => 'https://api.adoology.com',
        ));
        register_setting('adoology_settings', 'adoology_api_token', array(
            'type'              => 'string',
            'sanitize_callback' => array($this, 'sanitize_api_token'),
            'default'           => '',
        ));

        foreach (array('adoology_tracking_enabled', 'adoology_fraud_enabled', 'adoology_order_form_enabled') as $option) {
            register_setting('adoology_features', $option, array(
                'type'              => 'string',
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default'           => $option === 'adoology_tracking_enabled' ? 'no' : 'yes',
            ));
        }
        foreach (array(
            'adoology_incomplete_timeout_minutes',
            'adoology_incomplete_expire_days',
            'adoology_fraud_rate_limit',
            'adoology_duplicate_window_minutes',
            'adoology_fraud_flag_threshold',
            'adoology_fraud_hold_threshold',
            'adoology_fraud_block_threshold',
        ) as $option) {
            register_setting('adoology_features', $option, array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
            ));
        }
    }

    /**
     * Validate API base URL before storage.
     *
     * @param string $value Submitted URL.
     * @return string
     */
    public function sanitize_api_base_url($value) {
        $validated = Adoology_API_Client::validate_base_url($value);
        if (is_wp_error($validated)) {
            add_settings_error('adoology_api_base_url', 'invalid_api_url', $validated->get_error_message());
            return (string) Adoology_Options::get('adoology_api_base_url', 'https://api.adoology.com');
        }
        $current = (string) Adoology_Options::get('adoology_api_base_url', 'https://api.adoology.com');
        if ($current !== '' && !hash_equals($current, $validated)) {
            if (Adoology_Connection::is_connected()) {
                add_settings_error('adoology_api_base_url', 'api_url_locked', __('Disconnect the store before changing the Adoology API URL.', 'adoology-connector'));
                return $current;
            }
            Adoology_Options::delete('adoology_api_token');
            add_settings_error('adoology_api_base_url', 'api_url_changed', __('API URL changed. Enter the workspace API key again.', 'adoology-connector'), 'warning');
        }
        return $validated;
    }

    /**
     * Encrypt a submitted workspace token and never expose the stored value.
     *
     * @param string $value Submitted token.
     * @return string
     */
    public function sanitize_api_token($value) {
        $value  = trim(sanitize_text_field((string) $value));
        $stored = (string) Adoology_Options::get('adoology_api_token', '');
        if ($value === '') {
            return $stored;
        }
        if (strpos($value, Adoology_Crypto::PREFIX) === 0) {
            return is_wp_error(Adoology_Crypto::decrypt($value, 'adoology_api_token')) ? $stored : $value;
        }
        if (!Adoology_API_Client::is_valid_token($value)) {
            add_settings_error('adoology_api_token', 'invalid_api_token', __('Enter a valid Adoology workspace API key beginning with dc_.', 'adoology-connector'));
            return $stored;
        }

        $encrypted = Adoology_Crypto::encrypt($value, 'adoology_api_token');
        if (is_wp_error($encrypted)) {
            add_settings_error('adoology_api_token', 'token_encryption_failed', $encrypted->get_error_message());
            return $stored;
        }
        return $encrypted;
    }

    /**
     * Let WooCommerce managers save this settings group.
     *
     * @return string
     */
    public function settings_capability() {
        return 'manage_woocommerce';
    }

    /**
     * Normalize a checkbox option.
     *
     * @param mixed $value Submitted value.
     * @return string
     */
    public function sanitize_checkbox($value) {
        return $value === 'yes' ? 'yes' : 'no';
    }

    /**
     * Start WooCommerce authorization.
     */
    public function handle_connect() {
        $this->authorize_action('adoology_connect');
        $result = Adoology_Connection::connect();
        if (is_string($result)) {
            wp_safe_redirect($result);
            exit;
        }

        $this->redirect_with_result('adoology_connect', $result);
    }

    /**
     * Refresh backend connection state.
     */
    public function handle_test_connection() {
        $this->authorize_action('adoology_test_connection');
        $this->redirect_with_result('adoology_test', Adoology_Connection::refresh_status());
    }

    /**
     * Disconnect backend channel.
     */
    public function handle_disconnect() {
        $this->authorize_action('adoology_disconnect');
        $this->redirect_with_result('adoology_disconnect', Adoology_Connection::disconnect());
    }

    /**
     * Start manual product synchronization.
     */
    public function handle_sync_products() {
        $this->authorize_action('adoology_sync_products');
        $connection_id = Adoology_Connection::connection_id();
        $result = $connection_id === ''
            ? new WP_Error('adoology_not_connected', __('Connect the store before starting synchronization.', 'adoology-connector'))
            : Adoology_API_Client::start_product_sync($connection_id);
        $this->redirect_with_result('adoology_sync', is_wp_error($result) ? $result : true);
    }

    /**
     * Retry dead-letter and retrying event rows.
     */
    public function handle_retry_events() {
        global $wpdb;

        $this->authorize_action('adoology_retry_events');
        $wpdb->query("UPDATE " . Adoology_Database::events_table() . " SET status = 'pending', attempts = 0, available_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE status IN ('failed','retrying')");
        Adoology_Events::schedule_processing();
        $this->redirect_with_result('adoology_retry', true);
    }

    /**
     * Render admin page.
     */
    public function render_settings_page() {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $connection_id = Adoology_Connection::connection_id();
        $state         = Adoology_Options::get('adoology_connection_state', array());
        $state         = is_array($state) ? $state : array();
        $status        = isset($state['status']) ? sanitize_key((string) $state['status']) : 'not_connected';
        $token_set     = Adoology_Crypto::has_secret('adoology_api_token');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Adoology', 'adoology-connector'); ?></h1>
            <?php settings_errors(); ?>
            <?php $this->render_action_notice(); ?>

            <h2><?php esc_html_e('Connection', 'adoology-connector'); ?></h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php esc_html_e('Status', 'adoology-connector'); ?></th>
                    <td><strong><?php echo esc_html(ucwords(str_replace('_', ' ', $status))); ?></strong></td>
                </tr>
                <?php if ($connection_id !== '') : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Connection ID', 'adoology-connector'); ?></th>
                        <td><code><?php echo esc_html($connection_id); ?></code></td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($state['checked_at'])) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Last checked', 'adoology-connector'); ?></th>
                        <td><?php echo esc_html((string) $state['checked_at']); ?> UTC</td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($state['last_full_sync_at'])) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Last full sync', 'adoology-connector'); ?></th>
                        <td><?php echo esc_html((string) $state['last_full_sync_at']); ?></td>
                    </tr>
                <?php endif; ?>
                <?php if (isset($state['error_count'])) : ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('API errors', 'adoology-connector'); ?></th>
                        <td><?php echo esc_html((string) (int) $state['error_count']); ?></td>
                    </tr>
                <?php endif; ?>
            </table>

            <h2><?php esc_html_e('Credentials', 'adoology-connector'); ?></h2>
            <form method="post" action="options.php">
                <?php settings_fields('adoology_settings'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="adoology_api_base_url"><?php esc_html_e('Adoology API URL', 'adoology-connector'); ?></label></th>
                        <td><input name="adoology_api_base_url" id="adoology_api_base_url" type="url" class="regular-text" value="<?php echo esc_attr((string) Adoology_Options::get('adoology_api_base_url', 'https://api.adoology.com')); ?>" required /></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="adoology_api_token"><?php esc_html_e('Workspace API Key', 'adoology-connector'); ?></label></th>
                        <td>
                            <input name="adoology_api_token" id="adoology_api_token" type="password" class="regular-text" value="" autocomplete="new-password" maxlength="512" placeholder="<?php echo $token_set ? esc_attr__('Key saved; leave blank to keep it', 'adoology-connector') : 'dc_...'; ?>" />
                            <p class="description"><?php esc_html_e('Stored encrypted. Existing keys are never rendered into HTML.', 'adoology-connector'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(__('Save Credentials', 'adoology-connector')); ?>
            </form>

            <?php if ($connection_id === '') : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('adoology_connect'); ?>
                    <input type="hidden" name="action" value="adoology_connect" />
                    <?php submit_button(__('Connect Store', 'adoology-connector'), 'primary', 'submit', false, $token_set ? array() : array('disabled' => 'disabled')); ?>
                </form>
            <?php else : ?>
                <p><?php esc_html_e('Adoology owns WooCommerce API-key provisioning, webhooks, and initial synchronization for this connection.', 'adoology-connector'); ?></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block; margin-right:8px;">
                    <?php wp_nonce_field('adoology_test_connection'); ?>
                    <input type="hidden" name="action" value="adoology_test_connection" />
                    <?php submit_button(__('Test Connection', 'adoology-connector'), 'secondary', 'submit', false); ?>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;" onsubmit="return confirm('<?php echo esc_js(__('Disconnect this store from Adoology?', 'adoology-connector')); ?>');">
                    <?php wp_nonce_field('adoology_disconnect'); ?>
                    <input type="hidden" name="action" value="adoology_disconnect" />
                    <?php submit_button(__('Disconnect', 'adoology-connector'), 'delete', 'submit', false); ?>
                </form>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render operational dashboard.
     */
    public function render_dashboard() {
        global $wpdb;

        $this->guard_page();
        $state = Adoology_Options::get('adoology_connection_state', array());
        $state = is_array($state) ? $state : array();
        $events = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM " . Adoology_Database::events_table() . ' GROUP BY status', OBJECT_K);
        $incomplete = $wpdb->get_results("SELECT status, COUNT(*) AS total FROM " . Adoology_Database::incomplete_table() . ' GROUP BY status', OBJECT_K);
        $risk_orders = wc_get_orders(array(
            'limit'      => 1,
            'paginate'   => true,
            'meta_query' => array(array(
                'key'     => '_adoology_risk_score',
                'value'   => max(1, (int) Adoology_Options::get('adoology_fraud_flag_threshold', 30)),
                'compare' => '>=',
                'type'    => 'NUMERIC',
            )),
        ));
        $cards = array(
            __('Connection', 'adoology-connector')       => ucfirst((string) ($state['status'] ?? 'not connected')),
            __('Queued events', 'adoology-connector')    => $this->group_count($events, array('pending', 'retrying')),
            __('Sent events', 'adoology-connector')      => $this->group_count($events, array('sent')),
            __('Incomplete orders', 'adoology-connector') => $this->group_count($incomplete, array('incomplete')),
            __('Recovered orders', 'adoology-connector') => $this->group_count($incomplete, array('recovered')),
            __('Flagged orders', 'adoology-connector')   => is_object($risk_orders) && isset($risk_orders->total) ? (int) $risk_orders->total : 0,
            __('API errors', 'adoology-connector')       => (int) ($state['error_count'] ?? 0),
            __('Last successful sync', 'adoology-connector') => (string) ($state['last_full_sync_at'] ?? __('Not available', 'adoology-connector')),
        );
        ?>
        <div class="wrap"><h1><?php esc_html_e('Adoology Dashboard', 'adoology-connector'); ?></h1>
            <?php $this->render_action_notice(); ?>
            <?php $this->admin_styles(); ?>
            <div class="adoology-cards">
                <?php foreach ($cards as $label => $value) : ?>
                    <div class="adoology-card"><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html((string) $value); ?></strong></div>
                <?php endforeach; ?>
            </div>
            <p><a class="button button-primary" href="<?php echo esc_url(admin_url('admin.php?page=adoology-sync')); ?>"><?php esc_html_e('Open synchronization', 'adoology-connector'); ?></a> <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=adoology-incomplete')); ?>"><?php esc_html_e('View incomplete orders', 'adoology-connector'); ?></a></p>
        </div>
        <?php
    }

    /**
     * Render synchronization controls and recent backend runs.
     */
    public function render_sync() {
        $this->guard_page();
        $connection_id = Adoology_Connection::connection_id();
        $runs          = $connection_id ? Adoology_API_Client::get_sync_runs($connection_id) : new WP_Error('not_connected', __('Store is not connected.', 'adoology-connector'));
        ?>
        <div class="wrap"><h1><?php esc_html_e('Adoology Sync', 'adoology-connector'); ?></h1>
            <?php $this->render_action_notice(); ?>
            <p><?php esc_html_e('Adoology automatically imports products, orders, and customers after WooCommerce authorization. Product sync can also be started manually.', 'adoology-connector'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('adoology_sync_products'); ?><input type="hidden" name="action" value="adoology_sync_products" />
                <?php submit_button(__('Sync Products Now', 'adoology-connector'), 'primary', 'submit', false, $connection_id ? array() : array('disabled' => 'disabled')); ?>
            </form>
            <h2><?php esc_html_e('Recent Runs', 'adoology-connector'); ?></h2>
            <?php if (is_wp_error($runs)) : ?><div class="notice notice-warning inline"><p><?php echo esc_html($runs->get_error_message()); ?></p></div>
            <?php else : ?>
                <table class="widefat striped"><thead><tr><th><?php esc_html_e('Resource', 'adoology-connector'); ?></th><th><?php esc_html_e('Mode', 'adoology-connector'); ?></th><th><?php esc_html_e('Status', 'adoology-connector'); ?></th><th><?php esc_html_e('Processed', 'adoology-connector'); ?></th><th><?php esc_html_e('Failed', 'adoology-connector'); ?></th><th><?php esc_html_e('Updated', 'adoology-connector'); ?></th></tr></thead><tbody>
                <?php foreach ((array) ($runs['data'] ?? array()) as $run) : $attributes = (array) ($run['attributes'] ?? array()); ?>
                    <tr><td><?php echo esc_html((string) ($attributes['resource'] ?? '')); ?></td><td><?php echo esc_html((string) ($attributes['mode'] ?? '')); ?></td><td><?php echo esc_html((string) ($attributes['status'] ?? '')); ?></td><td><?php echo esc_html((string) (int) ($attributes['records_processed'] ?? 0)); ?></td><td><?php echo esc_html((string) (int) ($attributes['records_failed'] ?? 0)); ?></td><td><?php echo esc_html((string) ($attributes['updated_at'] ?? '')); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render tracked incomplete orders.
     */
    public function render_incomplete_orders() {
        global $wpdb;

        $this->guard_page();
        $rows = $wpdb->get_results("SELECT * FROM " . Adoology_Database::incomplete_table() . ' ORDER BY updated_at DESC LIMIT 100', ARRAY_A);
        ?>
        <div class="wrap"><h1><?php esc_html_e('Incomplete Orders', 'adoology-connector'); ?></h1>
            <p><?php esc_html_e('Checkout and landing-page starts are marked incomplete after the configured inactivity window. Recovery automation remains in Adoology.', 'adoology-connector'); ?></p>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Status', 'adoology-connector'); ?></th><th><?php esc_html_e('Flow', 'adoology-connector'); ?></th><th><?php esc_html_e('Customer', 'adoology-connector'); ?></th><th><?php esc_html_e('Product', 'adoology-connector'); ?></th><th><?php esc_html_e('Value', 'adoology-connector'); ?></th><th><?php esc_html_e('Stage', 'adoology-connector'); ?></th><th><?php esc_html_e('Last activity', 'adoology-connector'); ?></th><th><?php esc_html_e('Order', 'adoology-connector'); ?></th></tr></thead><tbody>
            <?php if (!$rows) : ?><tr><td colspan="8"><?php esc_html_e('No tracked checkouts.', 'adoology-connector'); ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $row) : $customer = $this->checkout_customer($row); $product = wc_get_product((int) ($row['variation_id'] ?: $row['product_id'])); ?>
                <tr><td><strong><?php echo esc_html(ucfirst($row['status'])); ?></strong></td><td><?php echo esc_html(str_replace('_', ' ', $row['flow'])); ?></td><td><?php echo esc_html(trim(($customer['name'] ?? '') . ' ' . ($customer['phone'] ?? '')) ?: __('Anonymous', 'adoology-connector')); ?></td><td><?php echo esc_html($product ? $product->get_name() : '#' . $row['product_id']); ?> × <?php echo esc_html((string) $row['quantity']); ?></td><td><?php echo wp_kses_post(wc_price(((int) $row['value_minor']) / pow(10, wc_get_price_decimals()), array('currency' => $row['currency']))); ?></td><td><?php echo esc_html($row['form_stage']); ?></td><td><?php echo esc_html($row['last_activity_at']); ?> UTC</td><td><?php if ($row['order_id']) : ?><a href="<?php echo esc_url($this->order_edit_url($row['order_id'])); ?>">#<?php echo esc_html((string) $row['order_id']); ?></a><?php else : ?>—<?php endif; ?></td></tr>
            <?php endforeach; ?></tbody></table>
        </div>
        <?php
    }

    /**
     * Render fraud settings and assessed orders.
     */
    public function render_fraud() {
        $this->guard_page();
        $threshold = max(1, (int) Adoology_Options::get('adoology_fraud_flag_threshold', 30));
        $orders = wc_get_orders(array(
            'limit'      => 50,
            'orderby'    => 'date',
            'order'      => 'DESC',
            'meta_query' => array(array('key' => '_adoology_risk_score', 'value' => $threshold, 'compare' => '>=', 'type' => 'NUMERIC')),
        ));
        ?>
        <div class="wrap"><h1><?php esc_html_e('Fraud Protection', 'adoology-connector'); ?></h1>
            <p><?php esc_html_e('Signals use direct IP velocity, duplicate contacts/products, invalid contact data, suspicious user agents, and a server-side honeypot.', 'adoology-connector'); ?></p>
            <p><strong><?php esc_html_e('Actions:', 'adoology-connector'); ?></strong> <?php printf(esc_html__('Flag at %1$d, hold at %2$d, block at %3$d.', 'adoology-connector'), $threshold, (int) Adoology_Options::get('adoology_fraud_hold_threshold', 60), (int) Adoology_Options::get('adoology_fraud_block_threshold', 90)); ?></p>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Order', 'adoology-connector'); ?></th><th><?php esc_html_e('Score', 'adoology-connector'); ?></th><th><?php esc_html_e('Action', 'adoology-connector'); ?></th><th><?php esc_html_e('Signals', 'adoology-connector'); ?></th><th><?php esc_html_e('Status', 'adoology-connector'); ?></th></tr></thead><tbody>
            <?php if (!$orders) : ?><tr><td colspan="5"><?php esc_html_e('No flagged orders.', 'adoology-connector'); ?></td></tr><?php endif; ?>
            <?php foreach ($orders as $order) : ?><tr><td><a href="<?php echo esc_url($this->order_edit_url($order->get_id())); ?>">#<?php echo esc_html((string) $order->get_id()); ?></a></td><td><?php echo esc_html((string) $order->get_meta('_adoology_risk_score', true)); ?>/100</td><td><?php echo esc_html((string) $order->get_meta('_adoology_risk_action', true)); ?></td><td><?php echo esc_html(implode(', ', (array) json_decode((string) $order->get_meta('_adoology_risk_signals', true), true))); ?></td><td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    /**
     * Render form usage documentation.
     */
    public function render_order_form() {
        $this->guard_page();
        ?>
        <div class="wrap"><h1><?php esc_html_e('Adoology Order Form', 'adoology-connector'); ?></h1>
            <p><?php esc_html_e('Creates a normal WooCommerce order without requiring the WooCommerce cart. Payment gateways continue through WooCommerce order-pay pages.', 'adoology-connector'); ?></p>
            <h2><?php esc_html_e('Shortcode', 'adoology-connector'); ?></h2><p><code>[adoology_order_form product_id="123"]</code></p>
            <p><code>[adoology_order_form product_id="123" title="Order today" delivery_options="standard:Standard Delivery,pickup:Pickup"]</code></p>
            <h2><?php esc_html_e('Block', 'adoology-connector'); ?></h2><p><?php esc_html_e('Add the “Adoology Order Form” block and enter a WooCommerce product ID in its sidebar.', 'adoology-connector'); ?></p>
        </div>
        <?php
    }

    /**
     * Render feature settings.
     */
    public function render_feature_settings() {
        $this->guard_page();
        ?>
        <div class="wrap"><h1><?php esc_html_e('Adoology Settings', 'adoology-connector'); ?></h1><?php settings_errors(); ?>
            <p><?php esc_html_e('Enable incomplete-order tracking only after updating the store privacy policy and obtaining any consent required in your jurisdiction.', 'adoology-connector'); ?></p>
            <form method="post" action="options.php"><?php settings_fields('adoology_features'); ?>
                <table class="form-table" role="presentation">
                    <?php $this->checkbox_row('adoology_tracking_enabled', __('Incomplete-order tracking', 'adoology-connector')); ?>
                    <?php $this->number_row('adoology_incomplete_timeout_minutes', __('Mark incomplete after (minutes)', 'adoology-connector'), 5, 1440); ?>
                    <?php $this->number_row('adoology_incomplete_expire_days', __('Expire data after (days)', 'adoology-connector'), 1, 90); ?>
                    <?php $this->checkbox_row('adoology_fraud_enabled', __('Fraud and bot protection', 'adoology-connector')); ?>
                    <?php $this->number_row('adoology_fraud_rate_limit', __('Order attempts per 10 minutes', 'adoology-connector'), 2, 100); ?>
                    <?php $this->number_row('adoology_duplicate_window_minutes', __('Duplicate-order window (minutes)', 'adoology-connector'), 5, 1440); ?>
                    <?php $this->number_row('adoology_fraud_flag_threshold', __('Flag threshold', 'adoology-connector'), 1, 100); ?>
                    <?php $this->number_row('adoology_fraud_hold_threshold', __('Hold threshold', 'adoology-connector'), 1, 100); ?>
                    <?php $this->number_row('adoology_fraud_block_threshold', __('Block threshold', 'adoology-connector'), 1, 100); ?>
                    <?php $this->checkbox_row('adoology_order_form_enabled', __('Landing-page order form', 'adoology-connector')); ?>
                </table><?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render event delivery log.
     */
    public function render_logs() {
        global $wpdb;

        $this->guard_page();
        $rows = $wpdb->get_results("SELECT event_id, event_name, status, attempts, last_error, created_at, sent_at FROM " . Adoology_Database::events_table() . ' ORDER BY id DESC LIMIT 100', ARRAY_A);
        ?>
        <div class="wrap"><h1><?php esc_html_e('Adoology Logs', 'adoology-connector'); ?></h1><?php $this->render_action_notice(); ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><?php wp_nonce_field('adoology_retry_events'); ?><input type="hidden" name="action" value="adoology_retry_events" /><?php submit_button(__('Retry Failed Events', 'adoology-connector'), 'secondary', 'submit', false); ?></form>
            <table class="widefat striped"><thead><tr><th><?php esc_html_e('Event', 'adoology-connector'); ?></th><th><?php esc_html_e('Status', 'adoology-connector'); ?></th><th><?php esc_html_e('Attempts', 'adoology-connector'); ?></th><th><?php esc_html_e('Error', 'adoology-connector'); ?></th><th><?php esc_html_e('Created', 'adoology-connector'); ?></th><th><?php esc_html_e('Sent', 'adoology-connector'); ?></th></tr></thead><tbody>
            <?php if (!$rows) : ?><tr><td colspan="6"><?php esc_html_e('No event deliveries.', 'adoology-connector'); ?></td></tr><?php endif; ?>
            <?php foreach ($rows as $row) : ?><tr><td><code><?php echo esc_html($row['event_name']); ?></code><br><small><?php echo esc_html($row['event_id']); ?></small></td><td><?php echo esc_html($row['status']); ?></td><td><?php echo esc_html((string) $row['attempts']); ?></td><td><?php echo esc_html((string) $row['last_error']); ?></td><td><?php echo esc_html($row['created_at']); ?> UTC</td><td><?php echo esc_html((string) $row['sent_at']); ?></td></tr><?php endforeach; ?>
            </tbody></table>
        </div>
        <?php
    }

    private function guard_page() {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to manage Adoology.', 'adoology-connector'));
        }
    }

    private function group_count($groups, $statuses) {
        $total = 0;
        foreach ($statuses as $status) {
            if (isset($groups[$status]->total)) {
                $total += (int) $groups[$status]->total;
            }
        }
        return $total;
    }

    private function checkout_customer($row) {
        if (empty($row['customer_data'])) {
            return array();
        }
        $decrypted = Adoology_Crypto::decrypt($row['customer_data'], 'adoology_checkout_' . $row['checkout_id']);
        $decoded   = is_wp_error($decrypted) ? null : json_decode($decrypted, true);
        return is_array($decoded) ? $decoded : array();
    }

    private function order_edit_url($order_id) {
        $order = wc_get_order((int) $order_id);
        return $order ? $order->get_edit_order_url() : admin_url('edit.php?post_type=shop_order');
    }

    private function checkbox_row($option, $label) {
        $default = $option === 'adoology_tracking_enabled' ? 'no' : 'yes';
        ?><tr><th scope="row"><?php echo esc_html($label); ?></th><td><label><input type="checkbox" name="<?php echo esc_attr($option); ?>" value="yes" <?php checked(Adoology_Options::get($option, $default), 'yes'); ?> /> <?php esc_html_e('Enabled', 'adoology-connector'); ?></label></td></tr><?php
    }

    private function number_row($option, $label, $min, $max) {
        ?><tr><th scope="row"><label for="<?php echo esc_attr($option); ?>"><?php echo esc_html($label); ?></label></th><td><input type="number" class="small-text" id="<?php echo esc_attr($option); ?>" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr((string) Adoology_Options::get($option, $min)); ?>" min="<?php echo esc_attr((string) $min); ?>" max="<?php echo esc_attr((string) $max); ?>" /></td></tr><?php
    }

    private function admin_styles() {
        ?><style>.adoology-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;max-width:1100px;margin:20px 0}.adoology-card{padding:18px;border:1px solid #dcdcde;border-radius:10px;background:#fff}.adoology-card span{display:block;color:#646970;margin-bottom:12px}.adoology-card strong{display:block;font-size:24px;line-height:1.2}</style><?php
    }

    /**
     * Verify capability and action nonce.
     *
     * @param string $action Nonce action.
     */
    private function authorize_action($action) {
        $nonce = sanitize_text_field(wp_unslash($_POST['_wpnonce'] ?? ''));
        if (!current_user_can('manage_woocommerce') || !wp_verify_nonce($nonce, $action)) {
            wp_die(esc_html__('Security check failed.', 'adoology-connector'));
        }
    }

    /**
     * Redirect to admin page with a safe result flag.
     *
     * @param string        $action Action query key.
     * @param true|WP_Error $result Action result.
     */
    private function redirect_with_result($action, $result) {
        set_transient('adoology_admin_notice_' . get_current_user_id(), array(
            'action'  => sanitize_key($action),
            'status'  => is_wp_error($result) ? 'error' : 'ok',
            'message' => is_wp_error($result) ? Adoology_Logger::redact_string(sanitize_text_field($result->get_error_message())) : '',
        ), MINUTE_IN_SECONDS);
        $pages = array(
            'adoology_connect'    => 'adoology-connection',
            'adoology_test'       => 'adoology-connection',
            'adoology_disconnect' => 'adoology-connection',
            'adoology_sync'       => 'adoology-sync',
            'adoology_retry'      => 'adoology-logs',
        );
        wp_safe_redirect(add_query_arg('page', $pages[$action] ?? 'adoology', admin_url('admin.php')));
        exit;
    }

    /**
     * Render action result from allowlisted query flags.
     */
    private function render_action_notice() {
        $actions = array(
            'adoology_connect'    => __('Store connection checked.', 'adoology-connector'),
            'adoology_test'       => __('Adoology connection is reachable.', 'adoology-connector'),
            'adoology_disconnect' => __('Store disconnected from Adoology.', 'adoology-connector'),
            'adoology_sync'       => __('Product synchronization started.', 'adoology-connector'),
            'adoology_retry'      => __('Failed events queued for retry.', 'adoology-connector'),
        );
        $transient = 'adoology_admin_notice_' . get_current_user_id();
        $notice    = get_transient($transient);
        delete_transient($transient);
        if (!is_array($notice) || !isset($notice['action'], $notice['status']) || !isset($actions[$notice['action']])) {
            return;
        }
        if ($notice['status'] === 'ok') {
            printf('<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html($actions[$notice['action']]));
        } elseif ($notice['status'] === 'error') {
            printf('<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html((string) ($notice['message'] ?? '')));
        }
    }
}
