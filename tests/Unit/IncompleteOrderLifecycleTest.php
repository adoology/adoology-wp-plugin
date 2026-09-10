<?php

/**
 * Public checkout lifecycle regressions using the real encrypted event outbox.
 */

namespace Adoology\Tests\Unit;

use Adoology\Crypto;
use Adoology\Database;
use Adoology\IncompleteOrders;
use Adoology\OrderForm;
use Adoology\Plugin;
use Adoology\Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use ReflectionProperty;
use RuntimeException;
use stdClass;
use Throwable;
use WC_Customer;
use WC_Order;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

use function Brain\Monkey\Actions\expectAdded;
use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class IncompleteOrderLifecycleTest extends TestCase
{
    const CHECKOUT = '12345678-1234-4234-8234-123456789abc';

    const ANONYMOUS = '22345678-1234-4234-8234-123456789abc';

    const SESSION = '32345678-1234-4234-8234-123456789abc';

    const INSTANCE = '42345678-1234-4234-8234-123456789abc';

    const OTHER_INSTANCE = '52345678-1234-4234-8234-123456789abc';

    private $db;

    private $options = ['adoology_tracking_enabled' => 'yes', 'adoology_fraud_enabled' => 'no'];

    private $orders = [];

    /** @var array<int, array<string, mixed>> */
    private $metadata = [];

    /** @var array<string, false|Throwable> */
    private $metadata_failures = [];

    private $scheduled = [];

    private $session = [];

    /** @var stdClass&MockInterface */
    private $woocommerce;

    private $globals = [];

    protected function set_up()
    {
        parent::set_up();
        foreach (['_COOKIE', '_POST', '_SERVER', 'wpdb'] as $key) {
            $this->globals[$key] = [array_key_exists($key, $GLOBALS), $GLOBALS[$key] ?? null];
        }
        $_COOKIE = [];
        $_POST = [];
        $_SERVER = ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_USER_AGENT' => 'Lifecycle test'];
        $this->db = new IncompleteOrderLifecycleWpdb;
        $GLOBALS['wpdb'] = $this->db;

        // Only supply the missing REST value object; WooCommerce classes use Mockery.
        if (!class_exists('WP_REST_Response')) {
            class_alias(IncompleteOrderLifecycleResponse::class, 'WP_REST_Response');
        }
        when('__')->returnArg();
        when('wp_unslash')->returnArg();
        when('esc_url_raw')->returnArg();
        when('wp_salt')->justReturn('lifecycle-test-secret');
        when('get_current_blog_id')->justReturn(1);
        when('is_ssl')->justReturn(true);
        when('get_option')->alias(fn ($name, $default = false) => $this->options[$name] ?? $default);
        $uuid = 0;
        when('wp_generate_uuid4')->alias(function () use (&$uuid) {
            return sprintf('abcdef01-1234-4234-8234-%012d', ++$uuid);
        });
        when('wc_get_order')->alias(fn ($id) => $this->orders[$id] ?? false);
        when('wc_get_orders')->alias(function ($args) {
            $this->assertSame('ids', $args['return']);
            $this->assertSame('_adoology_checkout_accepted_' . $args['meta_value'], $args['meta_key']);
            foreach ($this->metadata as $id => $meta) {
                if (($meta[$args['meta_key']] ?? '') === $args['meta_value']) {
                    return [$id];
                }
            }

            return [];
        });
        $cart = Mockery::mock();
        $cart->shouldReceive('get_cart')->andReturn($this->snapshot()['items']);
        $cart->shouldReceive('get_total')->with('edit')->andReturn('25.00');
        /** @var stdClass&MockInterface $woocommerce */
        $woocommerce = Mockery::mock(stdClass::class);
        $this->woocommerce = $woocommerce;
        $this->woocommerce->session = $this->load_session();
        $this->woocommerce->cart = $cart;
        when('WC')->justReturn($this->woocommerce);
        when('wc_load_cart')->justReturn(null);
        when('wc_get_price_decimals')->justReturn(2);
        when('get_woocommerce_currency')->justReturn('BDT');
        when('wc_get_product')->alias(function ($id) {
            $product = Mockery::mock();
            $product->shouldReceive('get_name')->andReturn($id === 31 ? 'Cotton Shirt - Blue' : 'Leather Wallet');
            $product->shouldReceive('get_price')->andReturn('12.50');
            $product->shouldReceive('get_parent_id')->andReturn(30);
            $product->shouldReceive('get_type')->andReturn($id === 31 ? 'variation' : 'simple');
            $product->shouldReceive('is_purchasable', 'has_enough_stock', 'is_in_stock')->andReturn(true);
            $product->shouldReceive('is_sold_individually')->andReturn(false);

            return $product;
        });
        when('did_action')->justReturn(0);
        when('wp_next_scheduled')->justReturn(false);
        when('wp_schedule_single_event')->alias(function ($time, $hook, $args) {
            $this->scheduled[] = compact('time', 'hook', 'args');

            return true;
        });
        $logger = Mockery::mock();
        $logger->shouldReceive('log');
        when('wc_get_logger')->justReturn($logger);
        when('home_url')->alias(fn ($path = '') => 'https://woo.test' . $path);
        when('wp_parse_url')->alias('parse_url');
        when('wp_using_ext_object_cache')->justReturn(true);
        when('wp_cache_add')->justReturn(true);
        when('untrailingslashit')->alias(fn ($value) => rtrim($value, '/'));
        when('rest_ensure_response')->alias(fn ($response) => $response instanceof WP_REST_Response ? $response : new WP_REST_Response($response));
    }

    protected function tear_down()
    {
        foreach ($this->globals as $key => $saved) {
            if ($saved[0]) {
                $GLOBALS[$key] = $saved[1];
            } else {
                unset($GLOBALS[$key]);
            }
        }
        parent::tear_down();
    }

    /** @dataProvider unqualified_customers */
    public function test_new_snapshot_requires_both_trimmed_name_and_phone($customer)
    {
        $this->assertFalse(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot(['customer' => $customer])));
        $this->assertSame([], $this->db->rows);
        $this->assertSame(['GET_LOCK', 'START TRANSACTION', 'COMMIT', 'RELEASE_LOCK'], $this->db->operations);
    }

    public static function unqualified_customers()
    {
        return [
            'empty' => [[]],
            'name only' => [['name' => 'Ada Lovelace']],
            'phone only' => [['phone' => '+8801712345678']],
            'whitespace' => [['name' => " \t\n ", 'phone' => '   ']],
            'blank phone' => [['name' => 'Ada Lovelace', 'phone' => "\t"]],
            'blank name' => [['name' => ' ', 'phone' => '+8801712345678']],
        ];
    }

    public function test_qualified_snapshot_immediately_queues_encrypted_contact_and_all_products()
    {
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $row = $this->row();
        $this->assertSame('started', $row['status']);
        $this->assertStringStartsWith(Crypto::PREFIX, $row['customer_data']);
        $this->assertStringNotContainsString('Ada Lovelace', $row['customer_data']);
        $events = $this->events();
        $this->assertCount(1, $events);
        $this->assertSame('checkout.started', $events[0]['name']);
        $this->assertSame(self::ANONYMOUS, $events[0]['anonymous_id']);
        $this->assertSame(self::SESSION, $events[0]['session_id']);
        $this->assertSame(self::CHECKOUT, $events[0]['properties']['checkout_id']);
        $this->assertSame($this->snapshot()['customer'], $events[0]['properties']['customer']);
        $this->assertSame(2500, $events[0]['properties']['value_minor']);
        $this->assertSame('Cotton Shirt - Blue', $events[0]['properties']['product_name']);
        $this->assertSame([
            ['product_id' => 30, 'variation_id' => 31, 'quantity' => 2, 'product_name' => 'Cotton Shirt - Blue'],
            ['product_id' => 40, 'variation_id' => 0, 'quantity' => 1, 'product_name' => 'Leather Wallet'],
        ], $events[0]['properties']['items']);
        $this->assertContains('COMMIT', $this->db->operations);
    }

    public function test_absent_contact_fields_merge_without_duplicate_row_or_unchanged_event()
    {
        $data = $this->snapshot();
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $data));
        $id = $this->row()['id'];
        unset($data['customer']);
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $data));
        $this->assertCount(1, $this->events());
        $data['customer'] = ['email' => 'new@example.test'];
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $data));
        $this->assertCount(1, $this->db->rows[Database::incomplete_table()]);
        $this->assertSame($id, $this->row()['id']);
        $customer = $this->events()[1]['properties']['customer'];
        $this->assertSame('Ada Lovelace', $customer['name']);
        $this->assertSame('+8801712345678', $customer['phone']);
        $this->assertSame('new@example.test', $customer['email']);
    }

    public function test_intentionally_cleared_contact_fields_are_stored_and_sent()
    {
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $cleared = ['name' => ' ', 'phone' => '', 'email' => '', 'address' => "\t"];
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot(['customer' => $cleared])));
        $expected = array_fill_keys(array_keys($cleared), '');
        $event = $this->events()[1];
        $this->assertSame('checkout.updated', $event['name']);
        $this->assertSame($expected, array_intersect_key($event['properties']['customer'], $expected));
        $stored = json_decode(Crypto::decrypt($this->row()['customer_data'], 'adoology_checkout_' . self::CHECKOUT), true);
        $this->assertSame($expected, array_intersect_key($stored, $expected));
        $this->assertSame('started', $this->row()['status']);
    }

    /** @dataProvider snapshot_edits */
    public function test_non_contact_edits_queue_updates_before_inactivity($change, $field, $expected)
    {
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot($change)));
        $events = $this->events();
        $this->assertSame(['checkout.started', 'checkout.updated'], array_column($events, 'name'));
        $this->assertSame('started', $this->row()['status']);
        $this->assertSame($expected, $events[1]['properties'][$field]);
        $this->assertCount(1, $this->db->rows[Database::incomplete_table()]);
    }

    public static function snapshot_edits()
    {
        return [
            'address only' => [['customer' => ['address' => '2 New Road']], 'customer', [
                'name' => 'Ada Lovelace', 'phone' => '+8801712345678', 'email' => 'ada@example.test', 'address' => '2 New Road',
            ]],
            'cart membership only' => [['items' => [['product_id' => 40, 'quantity' => 3]]], 'items', [
                ['product_id' => 40, 'variation_id' => 0, 'quantity' => 3, 'product_name' => 'Leather Wallet'],
            ]],
            'quantity only' => [['quantity' => 3], 'quantity', 3],
            'variation only' => [['variation_id' => 0], 'variation_id', 0],
            'value only' => [['value_minor' => 4000], 'value_minor', 4000],
            'currency only' => [['currency' => 'USD'], 'currency', 'USD'],
        ];
    }

    public function test_stale_pagehide_and_equal_sequences_cannot_restore_older_contact()
    {
        $data = $this->snapshot(['capture_instance' => self::INSTANCE, 'capture_sequence' => 1]);
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $data));
        $latest = array_replace($data, ['capture_sequence' => 3, 'customer' => ['phone' => '']]);
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $latest));
        $row = $this->row();
        $events = $this->events();
        foreach ([3, 2, 1] as $sequence) {
            $stale = array_replace($data, ['capture_sequence' => $sequence, 'form_stage' => 'leaving']);
            $this->assertFalse(IncompleteOrders::store_snapshot(self::CHECKOUT, $stale));
            $this->assertSame($row, $this->row());
            $this->assertSame($events, $this->events());
        }
        $this->assertSame('', $this->stored_customer()['phone']);
        $this->assertSame([self::INSTANCE => 3], $this->stored_customer()['_capture_sequences']);
        $this->assertSame(['checkout.started', 'checkout.updated'], array_column($events, 'name'));
    }

    public function test_new_document_generation_rejects_delayed_old_document_even_with_higher_sequence()
    {
        $old = $this->snapshot(['capture_instance' => self::INSTANCE, 'capture_generation' => 10, 'capture_sequence' => 1]);
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $old));
        $new = array_replace($old, [
            'capture_instance' => self::OTHER_INSTANCE, 'capture_generation' => 20,
            'customer' => ['name' => 'Grace Hopper', 'phone' => '+8801999999999'],
        ]);
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $new));
        $this->assertSame(20, $this->stored_customer()['_capture_generation']);
        $this->assertSame([self::OTHER_INSTANCE => 1], $this->stored_customer()['_capture_sequences']);
        $row = $this->row();
        $events = $this->events();
        $old['capture_sequence'] = 2;
        $this->assertFalse(IncompleteOrders::store_snapshot(self::CHECKOUT, $old));
        $this->assertSame($row, $this->row());
        $this->assertSame($events, $this->events());
        $this->assertSame('Grace Hopper', $this->stored_customer()['name']);
        $this->assertSame('+8801999999999', $this->stored_customer()['phone']);
        $this->assertSame(['checkout.started', 'checkout.updated'], array_column($events, 'name'));
        $new['capture_sequence'] = 2;
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $new));
        $this->assertSame($events, $this->events());
        foreach (['_capture_generation', 'capture_generation'] as $private) {
            $this->assertArrayNotHasKey($private, $this->row());
            $this->assertStringNotContainsString($private, $this->row()['customer_data']);
            $this->assertStringNotContainsString($private, wp_json_encode($events));
        }
    }

    public function test_sequence_only_updates_are_private_noops_and_contexts_advance_independently()
    {
        $data = $this->snapshot(['capture_instance' => self::INSTANCE, 'capture_sequence' => 1]);
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $data));
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, array_replace($data, [
            'capture_sequence' => 2, 'form_stage' => 'started',
        ])));
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, array_replace($data, [
            'capture_instance' => self::OTHER_INSTANCE,
        ])));
        $this->assertCount(1, $this->events());
        $this->assertSame('details', $this->row()['form_stage']);
        $this->assertSame([self::INSTANCE => 2, self::OTHER_INSTANCE => 1], $this->stored_customer()['_capture_sequences']);
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, array_replace($data, [
            'capture_instance' => self::OTHER_INSTANCE, 'capture_sequence' => 2, 'customer' => ['address' => '2 New Road'],
        ])));
        $this->assertFalse(IncompleteOrders::store_snapshot(self::CHECKOUT, $data));
        $this->assertSame(['checkout.started', 'checkout.updated'], array_column($this->events(), 'name'));
        $this->assertSame('2 New Road', $this->events()[1]['properties']['customer']['address']);
        foreach (['_capture_sequences', 'capture_instance', 'capture_sequence', self::INSTANCE, self::OTHER_INSTANCE] as $private) {
            $this->assertArrayNotHasKey($private, $this->row());
            $this->assertStringNotContainsString($private, wp_json_encode($this->events()));
            $this->assertStringNotContainsString($private, $this->row()['customer_data']);
        }
    }

    public function test_enqueue_failure_rolls_back_sequence_so_same_capture_can_retry()
    {
        $data = $this->snapshot(['capture_instance' => self::INSTANCE, 'capture_sequence' => 1]);
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $data));
        $next = array_replace($data, ['capture_sequence' => 2, 'customer' => ['phone' => '']]);
        $this->db->failures['insert:' . Database::events_table()] = false;
        $this->assertInstanceOf(WP_Error::class, IncompleteOrders::store_snapshot(self::CHECKOUT, $next));
        $this->assertSame([self::INSTANCE => 1], $this->stored_customer()['_capture_sequences']);
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $next));
        $this->assertSame([self::INSTANCE => 2], $this->stored_customer()['_capture_sequences']);
        $this->assertSame('', $this->stored_customer()['phone']);
        $this->assertCount(2, $this->events());
    }

    /** @dataProvider store_failures */
    public function test_store_errors_roll_back_snapshot_and_outbox($existing, $operation, $table, $code)
    {
        if ($existing) {
            $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        }
        $before = $this->db->rows;
        $this->db->operations = [];
        $this->db->failures[$operation . ':wp_adoology_' . $table] = false;
        $result = IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot(['customer' => [
            'name' => 'New Name', 'phone' => '+8801712345678',
        ]]));
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame($code, $result->get_error_code());
        $this->assertSame($before, $this->db->rows);
        $this->assertSame(['ROLLBACK', 'RELEASE_LOCK'], array_slice($this->db->operations, -2));
        $this->assertNotContains('COMMIT', $this->db->operations);
    }

    public static function store_failures()
    {
        return [
            'snapshot insert' => [false, 'insert', 'incomplete_orders', 'adoology_checkout_store_failed'],
            'started enqueue' => [false, 'insert', 'events', 'adoology_event_store_failed'],
            'snapshot update' => [true, 'update', 'incomplete_orders', 'adoology_checkout_store_failed'],
            'updated enqueue' => [true, 'insert', 'events', 'adoology_event_store_failed'],
        ];
    }

    public function test_thrown_enqueue_error_rolls_back_and_releases_lock()
    {
        $this->db->failures['insert:' . Database::events_table()] = new RuntimeException('Database disconnected');
        try {
            IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot());
            $this->fail('Expected the database exception to propagate.');
        } catch (RuntimeException $error) {
            $this->assertSame('Database disconnected', $error->getMessage());
        }
        $this->assertSame([], $this->db->rows);
        $this->assertSame(['ROLLBACK', 'RELEASE_LOCK'], array_slice($this->db->operations, -2));
    }

    /** @dataProvider completion_statuses */
    public function test_completion_queues_terminal_event_before_deleting_snapshot_and_tombstones_replays($status, $terminal)
    {
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $this->db->update(Database::incomplete_table(), ['status' => $status], ['checkout_id' => self::CHECKOUT]);
        $order = $this->order();
        $started = $this->db->rows[Database::events_table()];
        $this->db->operations = [];
        IncompleteOrders::mark_complete(self::CHECKOUT, 501, $order);
        $this->assertNull($this->row());
        $events = $this->events();
        $this->assertSame(['checkout.started', 'checkout.' . $terminal], array_column($events, 'name'));
        $this->assertSame(['checkout_id' => self::CHECKOUT, 'order_id' => 501, 'status' => $terminal], $events[1]['properties']);
        $this->assertSame(self::ANONYMOUS, $events[1]['anonymous_id']);
        $this->assertSame(self::SESSION, $events[1]['session_id']);
        $this->assertSame($started, array_intersect_key($this->db->rows[Database::events_table()], $started));
        $this->assertLessThan(
            array_search('delete:' . Database::incomplete_table(), $this->db->operations, true),
            array_search('insert:' . Database::events_table(), $this->db->operations, true)
        );
        $this->assertSame($events[1]['id'], $this->metadata[501]['_adoology_checkout_completion_' . self::CHECKOUT]);
        $this->assertSame([
            'GET_LOCK', 'save_meta_data:501', 'read_meta_data:501',
            'START TRANSACTION',
            'insert:' . Database::events_table(), 'save_meta_data:501', 'read_meta_data:501',
            'COMMIT', 'delete:' . Database::incomplete_table(), 'RELEASE_LOCK',
        ], $this->db->operations);
        $this->assertSame(501, IncompleteOrders::accepted_order_id(self::CHECKOUT));
        $this->assertFalse(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $this->assertSame(['claimed' => false, 'order_id' => 501], IncompleteOrders::claim_submission(self::CHECKOUT));
        IncompleteOrders::mark_complete(self::CHECKOUT, 501, $order);
        $this->assertSame($events, $this->events());
        $this->assertSame($order, wc_get_order(501));
    }

    public static function completion_statuses()
    {
        return [
            'active' => ['started', 'converted'],
            'abandoned' => ['incomplete', 'recovered'],
            'recovery claim' => ['submitting_recovery', 'recovered'],
        ];
    }

    public function test_one_order_can_close_two_checkouts_without_losing_either_tombstone()
    {
        $order = $this->order();
        foreach ([self::CHECKOUT, self::INSTANCE] as $checkout_id) {
            $this->assertTrue(IncompleteOrders::store_snapshot($checkout_id, $this->snapshot()));
            IncompleteOrders::mark_complete($checkout_id, 501, $order);
            $this->assertNull($this->row($checkout_id));
        }
        $events = $this->events();
        $this->assertSame(['checkout.started', 'checkout.converted', 'checkout.started', 'checkout.converted'], array_column($events, 'name'));
        $this->assertNotSame($events[1]['id'], $events[3]['id']);
        foreach ([self::CHECKOUT => $events[1], self::INSTANCE => $events[3]] as $checkout_id => $event) {
            $this->assertSame($checkout_id, $this->metadata[501]['_adoology_checkout_accepted_' . $checkout_id]);
            $this->assertSame($event['id'], $this->metadata[501]['_adoology_checkout_completion_' . $checkout_id]);
            $this->assertSame($checkout_id, $event['properties']['checkout_id']);
            $this->assertSame(['claimed' => false, 'order_id' => 501], IncompleteOrders::claim_submission($checkout_id));
            $this->assertFalse(IncompleteOrders::store_snapshot($checkout_id, $this->snapshot()));
            IncompleteOrders::mark_complete($checkout_id, 501);
            $this->assertNull($this->row($checkout_id));
        }
        $this->assertSame($events, $this->events());
    }

    /** @dataProvider metadata_markers */
    public function test_failed_metadata_save_keeps_snapshot_until_durable_retry($marker)
    {
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $order = $this->order();
        $key = '_adoology_checkout_' . $marker . '_' . self::CHECKOUT;
        $this->metadata_failures[$key] = false;
        $this->db->operations = [];
        IncompleteOrders::mark_complete(self::CHECKOUT, 501, $order);
        $this->assertNotNull($this->row());
        $this->assertArrayNotHasKey($key, $this->metadata[501]);
        $this->assertSame('', $order->get_meta($key, true), 'Forced reads must discard unsaved cached markers.');
        $this->assertContains('read_meta_data:501', $this->db->operations);
        $this->assertNotContains('delete:' . Database::incomplete_table(), $this->db->operations);
        $this->assertSame(['checkout.started'], array_column($this->events(), 'name'));
        if ($marker === 'completion') {
            $this->assertContains('ROLLBACK', $this->db->operations);
            $this->assertNotContains('COMMIT', $this->db->operations);
            $this->assertSame(501, IncompleteOrders::accepted_order_id(self::CHECKOUT));
        } else {
            $this->assertNotContains('START TRANSACTION', $this->db->operations);
        }
        $retry = end($this->scheduled);
        $this->assertSame(IncompleteOrders::COMPLETION_HOOK, $retry['hook']);
        $this->assertSame([self::CHECKOUT, 501, null, 1], $retry['args']);
        call_user_func_array([IncompleteOrders::class, 'mark_complete'], $retry['args']);
        $this->assertNull($this->row());
        $this->assertSame(501, IncompleteOrders::accepted_order_id(self::CHECKOUT));
        $this->assertSame(['checkout.started', 'checkout.converted'], array_column($this->events(), 'name'));
    }

    public static function metadata_markers()
    {
        return ['acceptance save' => ['accepted'], 'completion save' => ['completion']];
    }

    /** @dataProvider cleanup_failures */
    public function test_completion_failure_preserves_order_and_retries_without_duplicate_terminal_event($operation, $table, $throws)
    {
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $order = $this->order();
        $this->db->failures[$operation . ':wp_adoology_' . $table] = $throws ? new RuntimeException('Database disconnected') : false;
        IncompleteOrders::mark_complete(self::CHECKOUT, 501, $order);
        $this->assertNotNull($this->row());
        $this->assertSame($order, wc_get_order(501));
        $this->assertSame(['claimed' => false, 'order_id' => 501], IncompleteOrders::claim_submission(self::CHECKOUT));
        $this->assertFalse(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $this->assertCount($operation === 'delete' ? 2 : 1, $this->events());
        $retries = array_values(array_filter($this->scheduled, fn ($job) => $job['hook'] === IncompleteOrders::COMPLETION_HOOK));
        $this->assertCount(1, $retries);
        $this->assertSame([self::CHECKOUT, 501, null, 1], $retries[0]['args']);
        $this->assertGreaterThanOrEqual(time() + MINUTE_IN_SECONDS - 2, $retries[0]['time']);
        call_user_func_array([IncompleteOrders::class, 'mark_complete'], $retries[0]['args']);
        $this->assertNull($this->row());
        $this->assertSame(['checkout.started', 'checkout.converted'], array_column($this->events(), 'name'));
        IncompleteOrders::mark_complete(self::CHECKOUT, 501);
        $this->assertCount(2, $this->events());
    }

    public static function cleanup_failures()
    {
        return [
            'terminal enqueue' => ['insert', 'events', false],
            'snapshot delete' => ['delete', 'incomplete_orders', false],
            'database exception' => ['insert', 'events', true],
        ];
    }

    /** @dataProvider cleanup_failures */
    public function test_running_completion_action_cannot_suppress_next_retry($operation, $table, $throws)
    {
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $this->order();
        $running = [self::CHECKOUT, 501, null, 1];
        $retries = [];
        when('did_action')->justReturn(1);
        when('as_has_scheduled_action')->alias(function ($hook, $args) use (&$running) {
            return $hook === IncompleteOrders::COMPLETION_HOOK && $args === $running;
        });
        when('as_schedule_single_action')->alias(function ($time, $hook, $args, $group, $unique) use (&$retries) {
            if ($hook === IncompleteOrders::COMPLETION_HOOK) {
                $this->assertTrue($unique);
                $retries[] = $args;
            }

            return 123;
        });
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->db->failures[$operation . ':wp_adoology_' . $table] = $throws ? new RuntimeException('Database disconnected') : false;
            call_user_func_array([IncompleteOrders::class, 'mark_complete'], $running);
            $this->assertCount($attempt, $retries);
            $this->assertSame([self::CHECKOUT, 501, null, $attempt + 1], $retries[$attempt - 1]);
            $this->assertNotNull($this->row());
            $running = $retries[$attempt - 1];
        }
        call_user_func_array([IncompleteOrders::class, 'mark_complete'], $running);
        $this->assertNull($this->row());
        $this->assertSame(['checkout.started', 'checkout.converted'], array_column($this->events(), 'name'));
    }

    public function test_tracking_disabled_keeps_only_operational_order_form_claim()
    {
        $this->options['adoology_tracking_enabled'] = 'no';
        $this->assertFalse(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot(['flow' => 'order_form', 'customer' => []])));
        $this->assertSame('', $this->row()['customer_data']);
        $this->assertSame(['claimed' => true, 'order_id' => 0], IncompleteOrders::claim_submission(self::CHECKOUT));
        $this->assertSame('submitting', $this->row()['status']);
        $this->assertInstanceOf(WP_Error::class, IncompleteOrders::claim_submission(self::CHECKOUT));
        $this->order();
        IncompleteOrders::mark_complete(self::CHECKOUT, 501);
        $this->assertNull($this->row());
        $this->assertSame('untracked', $this->metadata[501]['_adoology_checkout_completion_' . self::CHECKOUT]);
        $this->assertSame([], $this->events());
        $this->assertSame(['claimed' => false, 'order_id' => 501], IncompleteOrders::claim_submission(self::CHECKOUT));
    }

    public function test_classic_processed_only_binds_identity_until_payment_success()
    {
        $this->native_identity();
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $order = $this->order();
        IncompleteOrders::checkout_completed(501, ['_adoology_checkout_id' => self::ANONYMOUS], $order);
        $this->assertSame(self::CHECKOUT, $this->metadata[501]['_adoology_checkout_id']);
        $this->assertArrayNotHasKey('_adoology_checkout_accepted_' . self::CHECKOUT, $this->metadata[501]);
        $this->assertNotNull($this->row());
        $failure = ['result' => 'failure', 'messages' => 'Card declined'];
        $this->assertSame($failure, IncompleteOrders::payment_successful($failure, 501));
        $this->assertNotNull($this->row());
        $this->assertCount(1, $this->events());
        $success = ['result' => 'success', 'redirect' => 'https://woo.test/thank-you'];
        $this->assertSame($success, IncompleteOrders::payment_successful($success, 501));
        $this->assertNull($this->row());
        $this->assertCount(2, $this->events());
        $this->assertSame(self::CHECKOUT, IncompleteOrders::identity()['checkout_id']);
        $this->assertSame(self::CHECKOUT, $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]);
    }

    public function test_registered_hooks_separate_identity_binding_from_successful_completion()
    {
        expectAdded(IncompleteOrders::COMPLETION_HOOK)
            ->once()->with([IncompleteOrders::class, 'mark_complete'], 10, 4);
        IncompleteOrders::register();
        $this->assertSame(30, has_action('woocommerce_checkout_order_processed', [IncompleteOrders::class, 'checkout_completed']));
        $this->assertSame(30, has_action('woocommerce_store_api_checkout_order_processed', [IncompleteOrders::class, 'store_api_checkout_completed']));
        $this->assertSame(PHP_INT_MAX, has_filter('woocommerce_payment_successful_result', [IncompleteOrders::class, 'payment_successful']));
        $this->assertSame(PHP_INT_MAX, has_filter('woocommerce_checkout_no_payment_needed_redirect', [IncompleteOrders::class, 'no_payment_needed']));
        $this->assertSame(PHP_INT_MAX, has_filter('rest_request_after_callbacks', [IncompleteOrders::class, 'store_api_checkout_response']));
        $this->assertSame(10, has_action(IncompleteOrders::COMPLETION_HOOK, [IncompleteOrders::class, 'mark_complete']));
    }

    /**
     * Keep optional WP-Cron function definitions out of unrelated tests.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function test_deactivation_unschedules_every_completion_attempt()
    {
        $args = [[self::CHECKOUT, 501, null, 1], [self::CHECKOUT, 501, null, 2]];
        $time = time() + MINUTE_IN_SECONDS;
        when('did_action')->justReturn(1);
        $hooks = [];
        when('as_unschedule_all_actions')->alias(function ($hook) use (&$hooks) {
            $hooks[] = $hook;
        });
        when('_get_cron_array')->justReturn([$time => [IncompleteOrders::COMPLETION_HOOK => [
            ['args' => $args[0]], ['args' => $args[1]],
        ]]]);
        $removed = [];
        when('wp_unschedule_event')->alias(function ($timestamp, $hook, $arguments) use (&$removed) {
            $removed[] = [$timestamp, $hook, $arguments];
        });
        when('delete_option')->justReturn(true);
        Plugin::deactivate();
        $this->assertContains(IncompleteOrders::COMPLETION_HOOK, $hooks);
        $this->assertSame([
            [$time, IncompleteOrders::COMPLETION_HOOK, $args[0]],
            [$time, IncompleteOrders::COMPLETION_HOOK, $args[1]],
        ], $removed);
    }

    public function test_blocks_capture_and_processed_hooks_bind_server_identity_without_completing()
    {
        $this->native_identity();
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $order = $this->order();
        /** @var WP_REST_Request&MockInterface $request */
        $request = Mockery::mock(WP_REST_Request::class);
        $request->shouldReceive('get_route')->andReturn('/wc/store/v1/checkout');
        $request->shouldReceive('get_param')->with('extensions')->andReturn(['adoology' => ['checkout_id' => self::ANONYMOUS]]);
        IncompleteOrders::store_api_capture_identity($order, $request);
        IncompleteOrders::store_api_checkout_completed($order);
        $this->assertSame(self::CHECKOUT, $this->metadata[501]['_adoology_checkout_id']);
        $this->assertArrayNotHasKey('_adoology_checkout_accepted_' . self::CHECKOUT, $this->metadata[501]);
        $this->assertNotNull($this->row());
        $this->assertCount(1, $this->events());
    }

    /** @dataProvider customer_addresses */
    public function test_blocks_customer_capture_uses_billing_or_shipping_contact_immediately($billing)
    {
        $this->native_identity();
        $shipping_address = $this->address();
        $billing_address = $billing ? $shipping_address : array_fill_keys(array_keys($shipping_address), '');
        $billing_address['email'] = 'ada@example.test';
        // WooCommerce dispatches each /batch inner request to this cart hook.
        $batch = ['requests' => [[
            'method' => 'POST', 'path' => '/wc/store/v1/cart/update-customer',
            'body' => ['billing_address' => $billing_address, 'shipping_address' => $shipping_address],
        ]]];
        $request = $this->cart_request($batch['requests'][0]['body']);
        IncompleteOrders::store_api_cart_updated($this->customer($billing_address, $shipping_address), $request);
        $events = $this->events();
        $this->assertCount(1, $events);
        $this->assertSame('checkout.started', $events[0]['name']);
        $this->assertSame([
            'name' => 'Ada Lovelace', 'phone' => '+8801712345678', 'email' => 'ada@example.test',
            'address' => '1 Test Road Unit 2', 'city' => 'Dhaka', 'postcode' => '1200', 'country' => 'BD',
        ], $events[0]['properties']['customer']);
        $this->assertCount(2, $events[0]['properties']['items']);
        $this->assertSame(2500, $events[0]['properties']['value_minor']);
        $this->assertSame('started', $this->row()['status']);
        $this->assertSame($billing, $this->stored_customer()['_billing_contact']);
    }

    public static function customer_addresses()
    {
        return ['billing contact' => [true], 'shipping fallback' => [false]];
    }

    public function test_partial_store_api_request_preserves_omitted_contact_fields()
    {
        $this->native_identity();
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $request = $this->cart_request(['billing_address' => ['city' => 'Chattogram']]);
        IncompleteOrders::store_api_cart_updated($this->customer(['city' => 'Chattogram']), $request);
        $expected = $this->snapshot()['customer'] + ['city' => 'Chattogram'];
        $this->assertSame($expected, $this->events()[1]['properties']['customer']);
        $before = $this->events();
        IncompleteOrders::store_api_cart_updated($this->customer([]), $this->cart_request([]));
        $this->assertSame($before, $this->events());
        $this->assertSame($expected, array_intersect_key($this->stored_customer(), $expected));
    }

    public function test_explicit_billing_phone_clear_does_not_fall_back_to_shipping_phone()
    {
        $this->native_identity();
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $customer = $this->customer($this->address(['phone' => '']), $this->address(['phone' => '+8801999999999']));
        $request = $this->cart_request([
            'billing_address' => ['phone' => ''],
            'shipping_address' => ['phone' => '+8801999999999'],
        ]);
        IncompleteOrders::store_api_cart_updated($customer, $request);
        $this->assertSame('', $this->stored_customer()['phone']);
        $this->assertSame('', $this->events()[1]['properties']['customer']['phone']);
        $this->assertSame('Ada Lovelace', $this->events()[1]['properties']['customer']['name']);
        $this->assertSame('1 Test Road', $this->events()[1]['properties']['customer']['address']);
    }

    public function test_captured_billing_provenance_keeps_all_billing_clears_despite_populated_shipping()
    {
        $this->native_identity();
        $params = $this->capture_params($this->capture_context('checkout', self::INSTANCE, 10), [
            'billing_contact' => true, 'form_stage' => 'details',
        ]);
        $response = IncompleteOrders::capture($this->capture_request($params));
        $this->assertSame(['stored' => true], $response->get_data());
        $this->assertTrue($this->stored_customer()['_billing_contact']);
        $params['billing_contact'] = false;
        $params['capture_sequence'] = 2;
        $params['customer'] = [];
        $this->assertSame(['stored' => true], IncompleteOrders::capture($this->capture_request($params))->get_data());
        $this->assertTrue($this->stored_customer()['_billing_contact']);
        $this->assertCount(1, $this->events());

        $billing = array_fill_keys(array_keys($this->address()), '');
        $shipping = $this->address();
        $request = $this->cart_request(['billing_address' => $billing, 'shipping_address' => $shipping]);
        IncompleteOrders::store_api_cart_updated($this->customer($billing, $shipping), $request);
        $expected = array_fill_keys(['name', 'phone', 'email', 'address', 'city', 'postcode', 'country'], '');
        $this->assertSame($expected, array_intersect_key($this->stored_customer(), $expected));
        $events = $this->events();
        $this->assertSame(['checkout.started', 'checkout.updated'], array_column($events, 'name'));
        $this->assertSame($expected, $events[1]['properties']['customer']);
        $this->assertTrue($this->stored_customer()['_billing_contact']);
        $this->assertStringNotContainsString('_billing_contact', wp_json_encode($events));
        $this->assertStringNotContainsString('_billing_contact', $this->row()['customer_data']);
        IncompleteOrders::store_api_cart_updated($this->customer($billing, $shipping), $request);
        $this->assertSame($events, $this->events());
    }

    /** @dataProvider billing_flags */
    public function test_capture_billing_provenance_requires_boolean_true($flag, $expected)
    {
        $this->native_identity();
        $params = $this->capture_params($this->capture_context(), ['billing_contact' => $flag]);
        $response = IncompleteOrders::capture($this->capture_request($params));
        $this->assertSame(['stored' => true], $response->get_data());
        $this->assertSame($expected, $this->stored_customer()['_billing_contact']);
        $this->assertStringNotContainsString('billing_contact', wp_json_encode($this->events()));
    }

    public static function billing_flags()
    {
        return [
            'boolean true' => [true, true],
            'boolean false' => [false, false],
            'string true' => ['true', false],
            'integer one' => [1, false],
        ];
    }

    public function test_late_cart_updates_cannot_open_new_checkout_after_success()
    {
        $identity = IncompleteOrders::identity(true);
        $this->assertTrue(IncompleteOrders::store_snapshot($identity['checkout_id'], $this->snapshot($identity)));
        $this->order();
        IncompleteOrders::mark_complete($identity['checkout_id'], 501);
        $events = $this->events();
        foreach ([$_COOKIE, []] as $cookies) {
            $_COOKIE = $cookies;
            $this->woocommerce->session = $this->load_session();
            IncompleteOrders::store_api_cart_updated($this->customer($this->address()), $this->cart_request(['billing_address' => $this->address()]));
            $this->assertSame($identity, IncompleteOrders::identity());
            $this->assertSame([], $this->db->rows[Database::incomplete_table()]);
            $this->assertSame($events, $this->events());
        }
    }

    public function test_accepted_cookie_returns_before_identity_and_preserves_published_next_checkout()
    {
        $closed = IncompleteOrders::identity(true);
        $this->assertTrue(IncompleteOrders::store_snapshot($closed['checkout_id'], $this->snapshot($closed)));
        $old_cookies = $_COOKIE;
        $this->order();
        IncompleteOrders::mark_complete($closed['checkout_id'], 501);
        IncompleteOrders::issue_capture_token($this->capture_request($this->capture_context()));
        $active = $this->session['adoology_checkout_identity'];
        $this->assertNotSame($closed['checkout_id'], $active['checkout_id']);
        $this->assertSame($active['checkout_id'], $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]);
        $this->assertTrue(IncompleteOrders::store_snapshot($active['checkout_id'], $this->snapshot($active + [
            'customer' => ['name' => 'Grace Hopper', 'phone' => '+8801999999999'],
        ])));
        $before = $this->db->rows;
        $events = $this->events();
        $_COOKIE = $old_cookies;
        expect('WC')->never();
        IncompleteOrders::store_api_cart_updated($this->customer($this->address()), $this->cart_request(['billing_address' => $this->address()]));
        $this->assertSame($active, $this->session['adoology_checkout_identity']);
        $this->assertSame($old_cookies, $_COOKIE);
        $this->assertSame($before, $this->db->rows);
        $this->assertSame($events, $this->events());
    }

    public function test_empty_native_cart_never_creates_initial_snapshot()
    {
        $this->native_identity();
        $cart = Mockery::mock();
        $cart->shouldReceive('get_cart')->andReturn([]);
        $cart->shouldReceive('get_total')->with('edit')->andReturn('0');
        $this->woocommerce->cart = $cart;
        $this->assertFalse(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot(['items' => []])));
        IncompleteOrders::store_api_cart_updated($this->customer($this->address()), $this->cart_request(['billing_address' => $this->address()]));
        $this->assertSame([], $this->db->rows);
        $this->assertSame([], $this->events());
    }

    public function test_existing_order_checkout_preserves_saved_identity_through_success()
    {
        $native = $this->native_identity();
        $this->assertTrue(IncompleteOrders::store_snapshot(self::INSTANCE, $this->snapshot()));
        $order = $this->order();
        $this->metadata[501]['_adoology_checkout_id'] = self::INSTANCE;
        /** @var WP_REST_Request&MockInterface $request */
        $request = Mockery::mock(WP_REST_Request::class);
        $request->shouldReceive('get_route')->andReturn('/wc/store/v1/checkout/501');
        $request->shouldReceive('get_method')->andReturn('POST');
        IncompleteOrders::store_api_capture_identity($order, $request);
        IncompleteOrders::store_api_checkout_completed($order);
        $this->assertSame(self::INSTANCE, $this->metadata[501]['_adoology_checkout_id']);
        $response = new WP_REST_Response(['order_id' => 501, 'payment_result' => ['payment_status' => 'success']]);
        $this->assertSame($response, IncompleteOrders::store_api_checkout_response($response, [], $request));
        $this->assertNull($this->row(self::INSTANCE));
        $this->assertSame(501, IncompleteOrders::accepted_order_id(self::INSTANCE));
        $this->assertSame($native, IncompleteOrders::identity());
    }

    public function test_no_payment_needed_completes_free_order_and_preserves_redirect()
    {
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $order = $this->order();
        $this->metadata[501]['_adoology_checkout_id'] = self::CHECKOUT;
        $redirect = 'https://woo.test/order-received/501';
        $this->assertSame($redirect, IncompleteOrders::no_payment_needed($redirect, $order));
        $this->assertNull($this->row());
        $this->assertSame(501, IncompleteOrders::accepted_order_id(self::CHECKOUT));
    }

    /** @dataProvider blocks_responses */
    public function test_blocks_response_completes_only_successful_checkout_posts($method, $route, $status, $payment, $accepted)
    {
        $this->assertTrue(IncompleteOrders::store_snapshot(self::CHECKOUT, $this->snapshot()));
        $this->order();
        $this->metadata[501]['_adoology_checkout_id'] = self::CHECKOUT;
        $request = Mockery::mock();
        $request->shouldReceive('get_method')->andReturn($method);
        $request->shouldReceive('get_route')->andReturn($route);
        $response = $status === 0 ? new WP_Error('payment_failed', 'Declined') : new WP_REST_Response([
            'order_id' => 501,
            'payment_result' => ['payment_status' => $payment],
        ], $status);
        $this->assertSame($response, IncompleteOrders::store_api_checkout_response($response, [], $request));
        $this->assertSame($accepted, $this->row() === null);
        $this->assertCount($accepted ? 2 : 1, $this->events());
        $this->assertSame($accepted ? 501 : 0, IncompleteOrders::accepted_order_id(self::CHECKOUT));
    }

    public static function blocks_responses()
    {
        return [
            'v1 success' => ['POST', '/wc/store/v1/checkout', 200, 'success', true],
            'unversioned success' => ['POST', '/wc/store/checkout', 201, 'success', true],
            'trailing slash' => ['POST', '/wc/store/v1/checkout/', 202, 'success', true],
            'last 2xx status' => ['POST', '/wc/store/v1/checkout', 299, 'success', true],
            'failed' => ['POST', '/wc/store/v1/checkout', 200, 'failed', false],
            'pending' => ['POST', '/wc/store/v1/checkout', 200, 'pending', false],
            'missing payment status' => ['POST', '/wc/store/v1/checkout', 200, null, false],
            'WP_Error' => ['POST', '/wc/store/v1/checkout', 0, 'success', false],
            'redirect' => ['POST', '/wc/store/v1/checkout', 302, 'success', false],
            'first 3xx status' => ['POST', '/wc/store/v1/checkout', 300, 'success', false],
            'informational' => ['POST', '/wc/store/v1/checkout', 199, 'success', false],
            'client error' => ['POST', '/wc/store/v1/checkout', 400, 'success', false],
            'server error' => ['POST', '/wc/store/v1/checkout', 500, 'success', false],
            'GET' => ['GET', '/wc/store/v1/checkout', 200, 'success', false],
            'PUT' => ['PUT', '/wc/store/v1/checkout', 200, 'success', false],
            'PATCH' => ['PATCH', '/wc/store/v1/checkout', 200, 'success', false],
            'cart route' => ['POST', '/wc/store/v1/cart', 200, 'success', false],
            'existing order checkout' => ['POST', '/wc/store/v1/checkout/501', 200, 'success', true],
            'existing order unversioned' => ['POST', '/wc/store/checkout/501/', 200, 'success', true],
            'mismatched order id' => ['POST', '/wc/store/v1/checkout/502', 200, 'success', false],
            'unrelated checkout subroute' => ['POST', '/wc/store/v1/checkout/501/pay', 200, 'success', false],
        ];
    }

    public function test_new_native_identity_uses_uuid_session_and_persists_tuple()
    {
        $identity = IncompleteOrders::identity();
        foreach ($identity as $value) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $value);
        }
        $this->assertNotSame('42', $identity['session_id']);
        $this->assertSame($identity, $this->session['adoology_checkout_identity']);
        $this->assertSame($identity, IncompleteOrders::identity());
        $this->assertSame([], $_COOKIE);
        $this->assertSame($identity, IncompleteOrders::identity(true));
        $this->assertSame($identity['checkout_id'], $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]);
        $this->assertSame($identity['anonymous_id'], $_COOKIE[IncompleteOrders::COOKIE_ANON]);
    }

    /** @dataProvider native_cookies */
    public function test_independent_empty_wc_sessions_derive_same_initial_identity($cookies)
    {
        $identities = [];
        for ($request = 0; $request < 2; $request++) {
            $_COOKIE = $cookies;
            $this->woocommerce->session = $this->load_session('wc-guest-session-42');
            $identities[] = IncompleteOrders::identity();
            $this->assertSame($identities[$request], $this->session['adoology_checkout_identity']);
            $this->assertSame($cookies, $_COOKIE);
            if (isset($cookies[IncompleteOrders::COOKIE_CHECKOUT])) {
                $this->assertSame($cookies[IncompleteOrders::COOKIE_CHECKOUT], $identities[$request]['checkout_id']);
            }
        }
        $this->assertSame($identities[0], $identities[1]);
        $_COOKIE = [];
        $this->woocommerce->session = $this->load_session('different-wc-session');
        $other = IncompleteOrders::identity();
        foreach (array_keys($other) as $field) {
            $this->assertNotSame($identities[0][$field], $other[$field]);
        }
    }

    public static function native_cookies()
    {
        return [
            'no cookies' => [[]],
            'existing checkout cookie' => [[IncompleteOrders::COOKIE_CHECKOUT => self::CHECKOUT]],
        ];
    }

    public function test_independent_token_requests_derive_same_next_generation_after_acceptance()
    {
        $identity = IncompleteOrders::identity();
        $this->assertTrue(IncompleteOrders::store_snapshot($identity['checkout_id'], $this->snapshot($identity)));
        $this->order();
        IncompleteOrders::mark_complete($identity['checkout_id'], 501);
        $next = [];
        for ($request = 0; $request < 2; $request++) {
            $_COOKIE = [];
            $this->woocommerce->session = $this->load_session();
            $this->assertSame($identity, IncompleteOrders::identity());
            $token = IncompleteOrders::issue_capture_token($this->capture_request($this->capture_context()))->get_data();
            $next[] = array_intersect_key($token, $identity);
            $this->assertNotSame($identity['checkout_id'], $token['checkout_id']);
            $this->assertSame($identity['anonymous_id'], $token['anonymous_id']);
            $this->assertSame($identity['session_id'], $token['session_id']);
            $this->assertSame($next[$request], IncompleteOrders::identity());
        }
        $this->assertSame($next[0], $next[1]);
    }

    public function test_new_checkout_cookie_is_adopted_when_loaded_session_still_has_closed_generation()
    {
        $closed = $this->native_identity();
        $this->order();
        $this->metadata[501]['_adoology_checkout_accepted_' . self::CHECKOUT] = self::CHECKOUT;
        $next = IncompleteOrders::identity(true);
        $this->session['adoology_checkout_identity'] = $closed;
        $this->assertSame($next, IncompleteOrders::identity());
        $this->assertSame($next['checkout_id'], $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]);
    }

    public function test_tracker_localizes_rest_nonce_and_signed_capture_instance()
    {
        $context_property = new ReflectionProperty(IncompleteOrders::class, 'checkout_capture_context');
        $context_property->setAccessible(true);
        $previous = $context_property->getValue();
        when('plugins_url')->justReturn('https://woo.test/tracker.js');
        when('rest_url')->alias(fn ($path) => 'https://woo.test/wp-json/' . $path);
        expect('wp_create_nonce')->once()->with('wp_rest')->andReturn('rest-nonce');
        expect('wp_enqueue_script')->once()->with('adoology-checkout-tracker', 'https://woo.test/tracker.js', [], ADOOLOGY_VERSION, true);
        $config = [];
        when('wp_localize_script')->alias(function ($handle, $name, $data) use (&$config) {
            $this->assertSame('adoology-checkout-tracker', $handle);
            $this->assertSame('adoologyCheckout', $name);
            $config = $data;
        });
        try {
            $before = microtime(true);
            $signed = IncompleteOrders::enqueue_tracker('checkout', ['items' => $this->snapshot()['items']]);
            $after = microtime(true);
            $this->assertSame('rest-nonce', $config['restNonce']);
            $this->assertSame('https://woo.test/wp-json/adoology/v1/checkout-token', $config['tokenEndpoint']);
            $this->assertSame($signed['context'], $config['captureContext']);
            $this->assertSame($signed['signature'], $config['captureSignature']);
            $context = json_decode(base64_decode($signed['context']), true);
            $this->assertMatchesRegularExpression('/^[a-f0-9-]{36}$/', $context['instance']);
            $this->assertIsNumeric($context['issued_at']);
            $this->assertGreaterThanOrEqual($before, $context['issued_at']);
            $this->assertLessThanOrEqual($after, $context['issued_at']);
            $this->assertSame(hash_hmac('sha256', $signed['context'], 'lifecycle-test-secret'), $signed['signature']);
        } finally {
            $context_property->setValue(null, $previous);
        }
    }

    public function test_refreshed_native_capture_context_reuses_identity_but_rebinds_token()
    {
        $identity = IncompleteOrders::identity();
        $tokens = [];
        foreach ([self::INSTANCE, self::OTHER_INSTANCE] as $instance) {
            $context = $this->capture_context('checkout', $instance);
            $response = IncompleteOrders::issue_capture_token($this->capture_request($context));
            $this->assertInstanceOf(WP_REST_Response::class, $response);
            $this->assertSame(200, $response->get_status());
            $data = $response->get_data();
            $this->assertSame($identity, array_intersect_key($data, $identity));
            $message = implode('|', [$data['anonymous_id'], $data['checkout_id'], $data['session_id'], hash('sha256', $context['capture_context']), $data['expires']]);
            $this->assertSame($data['expires'] . '.' . hash_hmac('sha256', $message, 'lifecycle-test-secret'), $data['token']);
            $this->assertSame('no-store, private, max-age=0', $response->get_headers()['Cache-Control']);
            $tokens[] = $data['token'];
        }
        $this->assertNotSame($tokens[0], $tokens[1]);
        $this->assertSame($identity, IncompleteOrders::identity());
    }

    public function test_accepted_native_identity_rotates_before_new_capture_token()
    {
        $this->native_identity();
        $this->order();
        $this->metadata[501]['_adoology_checkout_accepted_' . self::CHECKOUT] = self::CHECKOUT;
        $response = IncompleteOrders::issue_capture_token($this->capture_request($this->capture_context()));
        $data = $response->get_data();
        $this->assertNotSame(self::CHECKOUT, $data['checkout_id']);
        $this->assertSame(self::ANONYMOUS, $data['anonymous_id']);
        $this->assertSame($data['checkout_id'], $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]);
        $this->assertSame(IncompleteOrders::identity(), array_intersect_key($data, $this->session['adoology_checkout_identity']));
    }

    public function test_order_form_tokens_do_not_replace_native_session_identity()
    {
        $native = $this->native_identity();
        $request = $this->capture_request($this->capture_context('order_form'));
        $first = IncompleteOrders::issue_capture_token($request)->get_data();
        $second = IncompleteOrders::issue_capture_token($request)->get_data();
        // Landing-flow identities are deterministic per browser (stable
        // across page regenerations) but stay distinct from the native
        // checkout identity.
        foreach (array_keys($native) as $field) {
            $this->assertNotSame($native[$field], $first[$field]);
            $this->assertSame($first[$field], $second[$field]);
        }
        $this->assertSame($native, IncompleteOrders::identity());
        $this->assertSame(self::CHECKOUT, $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT]);
    }

    /** @dataProvider unqualified_customers */
    public function test_capture_reports_not_stored_for_unqualified_contact($customer)
    {
        $context = $this->capture_context();
        $token = IncompleteOrders::issue_capture_token($this->capture_request($context))->get_data();
        $response = IncompleteOrders::capture($this->capture_request($context + [
            'anonymous_id' => $token['anonymous_id'],
            'checkout_id' => $token['checkout_id'],
            'session_id' => $token['session_id'],
            'capture_token' => $token['token'],
            'customer' => $customer,
        ]));
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(202, $response->get_status());
        $this->assertSame(['stored' => false], $response->get_data());
        $this->assertSame([], $this->db->rows);
    }

    public function test_capture_sequence_is_bound_to_signed_instance_not_client_selected_instance()
    {
        $identity = null;
        foreach ([self::INSTANCE, self::OTHER_INSTANCE] as $instance) {
            $context = $this->capture_context('checkout', $instance);
            $token = IncompleteOrders::issue_capture_token($this->capture_request($context))->get_data();
            $params = $context + [
                'anonymous_id' => $token['anonymous_id'], 'checkout_id' => $token['checkout_id'],
                'session_id' => $token['session_id'], 'capture_token' => $token['token'],
                'capture_instance' => wp_generate_uuid4(), 'capture_sequence' => 2,
                'customer' => $this->snapshot()['customer'],
            ];
            $response = IncompleteOrders::capture($this->capture_request($params));
            $this->assertSame(['stored' => true], $response->get_data());
            $identity = $token['checkout_id'];
            $stored = $this->stored_customer($identity);
            $this->assertSame(2, $stored['_capture_sequences'][$instance]);
            $this->assertArrayNotHasKey($params['capture_instance'], $stored['_capture_sequences']);
            $params['capture_instance'] = wp_generate_uuid4();
            $params['customer']['phone'] = '';
            $replay = IncompleteOrders::capture($this->capture_request($params));
            $this->assertSame(['stored' => false], $replay->get_data());
            $this->assertSame('+8801712345678', $this->stored_customer($identity)['phone']);
        }
        $this->assertSame([self::INSTANCE => 2, self::OTHER_INSTANCE => 2], $this->stored_customer($identity)['_capture_sequences']);
        $this->assertCount(1, $this->events());
        $this->assertStringNotContainsString('capture_sequence', wp_json_encode($this->events()));
    }

    /** @dataProvider older_capture_generations */
    public function test_signed_generation_overrides_forged_params_and_supersedes_older_contexts($issued_at)
    {
        $this->native_identity();
        $old = $this->capture_params($this->capture_context('checkout', self::INSTANCE, $issued_at), [
            'capture_generation' => 1000, 'issued_at' => 1000,
        ]);
        $this->assertSame(['stored' => true], IncompleteOrders::capture($this->capture_request($old))->get_data());
        $this->assertSame($issued_at ?? 0, $this->stored_customer()['_capture_generation']);
        $new = $this->capture_params($this->capture_context('checkout', self::OTHER_INSTANCE, 20), [
            'customer' => ['name' => 'Grace Hopper', 'phone' => '+8801999999999'],
        ]);
        $this->assertSame(['stored' => true], IncompleteOrders::capture($this->capture_request($new))->get_data());
        $this->assertSame(20, $this->stored_customer()['_capture_generation']);
        $this->assertSame([self::OTHER_INSTANCE => 1], $this->stored_customer()['_capture_sequences']);
        $row = $this->row();
        $events = $this->events();
        $old['capture_sequence'] = 2;
        $old['capture_instance'] = self::OTHER_INSTANCE;
        $this->assertSame(['stored' => false], IncompleteOrders::capture($this->capture_request($old))->get_data());
        $this->assertSame($row, $this->row());
        $this->assertSame($events, $this->events());
        $this->assertSame('Grace Hopper', $this->stored_customer()['name']);
        $this->assertSame(['checkout.started', 'checkout.updated'], array_column($events, 'name'));
    }

    public static function older_capture_generations()
    {
        return ['signed generation ten' => [10], 'legacy context without issued_at' => [null]];
    }

    /** @dataProvider order_form_outcomes */
    public function test_order_form_acceptance_preserves_order_even_when_cleanup_fails($tracking, $failure)
    {
        $this->options['adoology_tracking_enabled'] = $tracking;
        $native = $this->native_identity();
        $checkout_id = '42345678-1234-4234-8234-123456789abc';
        $order = $this->order();
        $order->shouldReceive('set_created_via')->once()->with('adoology-order-form');
        $order->shouldReceive('add_product', 'set_billing_phone', 'set_billing_email', 'add_item', 'calculate_totals');
        foreach (['billing', 'shipping'] as $type) {
            foreach (['first_name', 'last_name', 'address_1', 'city', 'postcode', 'country'] as $field) {
                $order->shouldReceive('set_' . $type . '_' . $field);
            }
        }
        $order->shouldReceive('save')->once()->andReturnUsing(function () use ($failure, $order) {
            $order->save_meta_data();
            if ($failure !== '') {
                $this->db->failures[$failure] = false;
            }
        });
        $order->shouldReceive('needs_payment')->andReturn(true);
        $order->shouldReceive('get_checkout_payment_url')->andReturn('https://woo.test/pay/501');
        $shipping = Mockery::mock('overload:WC_Order_Item_Shipping');
        $shipping->shouldReceive('set_method_title', 'set_method_id', 'set_total');
        $gateways = Mockery::mock();
        $gateways->shouldReceive('payment_gateways')->andReturn(['cod' => (object) ['id' => 'cod', 'enabled' => 'yes']]);
        $this->woocommerce->shouldReceive('payment_gateways')->andReturn($gateways);
        $countries = Mockery::mock();
        $countries->shouldReceive('get_base_country')->andReturn('BD');
        $this->woocommerce->countries = $countries;
        when('wp_get_referer')->justReturn('https://woo.test/order');
        when('wp_verify_nonce')->justReturn(true);
        when('sanitize_email')->returnArg();
        when('get_current_user_id')->justReturn(0);
        expect('wc_create_order')->once()->with(['customer_id' => 0])->andReturn($order);
        // WP-F23 regression: the native woocommerce_checkout_order_created hook owns
        // reservation; an explicit wc_reserve_stock_for_order call double-reserves and
        // rejects legitimate in-stock orders.
        expect('wc_reserve_stock_for_order')->never();
        $delivery = base64_encode(wp_json_encode(['standard' => ['label' => 'Standard', 'cost' => 0]]));
        $_POST = [
            '_adoology_nonce' => 'valid', '_adoology_checkout_id' => $checkout_id,
            'product_id' => 30, 'quantity' => 2, 'adoology_name' => 'Ada Lovelace',
            'adoology_phone' => '+8801712345678', 'adoology_email' => 'ada@example.test',
            'adoology_address' => '1 Test Road', 'adoology_city' => 'Dhaka',
            'delivery_config' => $delivery, 'delivery_signature' => hash_hmac('sha256', '30|' . $delivery, 'lifecycle-test-secret'),
            'delivery_option' => 'standard', 'payment_method' => 'cod',
        ];
        // Stop at the redirect instead of allowing submit() to exit PHPUnit.
        when('wp_safe_redirect')->alias(function ($url) {
            throw new IncompleteOrderLifecycleRedirect($url);
        });
        for ($attempt = 0; $attempt < 2; $attempt++) {
            try {
                OrderForm::submit();
                $this->fail('Expected the successful redirect.');
            } catch (IncompleteOrderLifecycleRedirect $redirect) {
                $this->assertSame('https://woo.test/pay/501', $redirect->getMessage());
            }
        }
        $this->assertSame($order, wc_get_order(501));
        $this->assertSame($checkout_id, $this->metadata[501]['_adoology_checkout_accepted_' . $checkout_id]);
        $this->assertSame($native, IncompleteOrders::identity());
        $this->assertSame($failure === '', $this->row($checkout_id) === null);
        $this->assertSame(['claimed' => false, 'order_id' => 501], IncompleteOrders::claim_submission($checkout_id));
        $this->assertCount($tracking === 'no' ? 0 : ($failure === 'insert:wp_adoology_events' ? 1 : 2), $this->events());
    }

    public static function order_form_outcomes()
    {
        return [
            'tracked' => ['yes', ''],
            'tracking disabled' => ['no', ''],
            'enqueue failure after acceptance' => ['yes', 'insert:wp_adoology_events'],
            'delete failure after acceptance' => ['yes', 'delete:wp_adoology_incomplete_orders'],
        ];
    }

    private function snapshot($changes = [])
    {
        return array_replace([
            'anonymous_id' => self::ANONYMOUS, 'session_id' => self::SESSION,
            'flow' => 'checkout', 'product_id' => 30, 'variation_id' => 31,
            'quantity' => 2, 'value_minor' => 2500, 'currency' => 'BDT',
            'form_stage' => 'details', 'landing_page' => 'https://woo.test/checkout',
            'customer' => ['name' => 'Ada Lovelace', 'phone' => '+8801712345678', 'email' => 'ada@example.test', 'address' => '1 Test Road'],
            'items' => [
                ['product_id' => 30, 'variation_id' => 31, 'quantity' => 2],
                ['product_id' => 40, 'variation_id' => 0, 'quantity' => 1],
            ],
        ], $changes);
    }

    private function row($checkout_id = self::CHECKOUT)
    {
        foreach ($this->db->rows[Database::incomplete_table()] ?? [] as $row) {
            if ($row['checkout_id'] === $checkout_id) {
                return $row;
            }
        }

        return null;
    }

    private function events()
    {
        $events = [];
        foreach ($this->db->rows[Database::events_table()] ?? [] as $row) {
            $this->assertStringStartsWith(Crypto::PREFIX, $row['payload']);
            $json = Crypto::decrypt($row['payload'], 'adoology_event_' . $row['event_id']);
            $this->assertIsString($json);
            $events[] = json_decode($json, true);
        }

        return $events;
    }

    private function stored_customer($checkout_id = self::CHECKOUT)
    {
        $json = Crypto::decrypt($this->row($checkout_id)['customer_data'], 'adoology_checkout_' . $checkout_id);
        $this->assertIsString($json);

        return json_decode($json, true);
    }

    /** @return WC_Order&MockInterface */
    private function order($id = 501)
    {
        /** @var WC_Order&MockInterface $order */
        $order = Mockery::mock(WC_Order::class);
        /** @var array<string, mixed>|null $cached */
        $cached = null;
        /** @var array<string, mixed> $pending */
        $pending = [];
        $this->metadata[$id] = [];
        $order->shouldReceive('get_id')->andReturn($id);
        $order->shouldReceive('get_meta')->andReturnUsing(function ($key) use ($id, &$cached) {
            $cached ??= $this->metadata[$id];

            return $cached[$key] ?? '';
        });
        $order->shouldReceive('update_meta_data')->andReturnUsing(function ($key, $value) use ($id, &$cached, &$pending) {
            $cached ??= $this->metadata[$id];
            $cached[$key] = $value;
            $pending[$key] = $value;
        });
        $order->shouldReceive('save_meta_data')->andReturnUsing(function () use ($id, &$pending) {
            $this->db->operations[] = 'save_meta_data:' . $id;
            foreach ($pending as $key => $value) {
                if (array_key_exists($key, $this->metadata_failures)) {
                    $failure = $this->metadata_failures[$key];
                    unset($this->metadata_failures[$key]);
                    if ($failure instanceof Throwable) {
                        throw $failure;
                    }

                    continue;
                }
                $this->metadata[$id][$key] = $value;
            }
            $pending = [];
        });
        // A failed save leaves dirty values cached until a forced storage read.
        $order->shouldReceive('read_meta_data')->with(true)->andReturnUsing(function () use ($id, &$cached, &$pending) {
            $this->db->operations[] = 'read_meta_data:' . $id;
            $cached = $this->metadata[$id];
            $pending = [];
        });
        $order->shouldNotReceive('delete');
        $this->orders[$id] = $order;

        return $order;
    }

    private function native_identity()
    {
        $_COOKIE[IncompleteOrders::COOKIE_ANON] = self::ANONYMOUS;
        $_COOKIE[IncompleteOrders::COOKIE_CHECKOUT] = self::CHECKOUT;
        $this->session['adoology_checkout_identity'] = [
            'anonymous_id' => self::ANONYMOUS, 'checkout_id' => self::CHECKOUT, 'session_id' => self::SESSION,
        ];

        return IncompleteOrders::identity();
    }

    private function load_session($customer_id = '42')
    {
        $session = Mockery::mock();
        $session->shouldReceive('get')->andReturnUsing(fn ($key) => $this->session[$key] ?? null);
        $session->shouldReceive('set')->andReturnUsing(function ($key, $value) {
            $this->session[$key] = $value;
        });
        $session->shouldReceive('get_customer_id')->andReturn($customer_id);
        $session->shouldReceive('set_customer_session_cookie')->with(true);
        $this->session = [];

        return $session;
    }

    private function address($changes = [])
    {
        return array_replace([
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'phone' => '+8801712345678',
            'email' => 'ada@example.test', 'address_1' => '1 Test Road', 'address_2' => 'Unit 2',
            'city' => 'Dhaka', 'postcode' => '1200', 'country' => 'BD',
        ], $changes);
    }

    /**
     * @param  array<string, string>  $billing
     * @param  array<string, string>  $shipping
     * @return WC_Customer&MockInterface
     */
    private function customer($billing, $shipping = [])
    {
        /** @var WC_Customer&MockInterface $customer */
        $customer = Mockery::mock(WC_Customer::class);
        foreach (['billing' => $billing, 'shipping' => $shipping] as $type => $address) {
            foreach (array_keys($this->address()) as $field) {
                $customer->shouldReceive('get_' . $type . '_' . $field)->andReturn($address[$field] ?? '');
            }
        }

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return WP_REST_Request&MockInterface
     */
    private function cart_request($params)
    {
        when('wp_get_referer')->justReturn('https://woo.test/checkout');
        /** @var WP_REST_Request&MockInterface $request */
        $request = Mockery::mock(WP_REST_Request::class);
        $request->shouldReceive('get_param')->andReturnUsing(fn ($key) => $params[$key] ?? null);

        return $request;
    }

    private function capture_context($flow = 'checkout', $instance = self::INSTANCE, $issued_at = null)
    {
        $data = [
            'flow' => $flow, 'product_id' => 30, 'instance' => $instance,
            'landing_page' => 'https://woo.test/checkout', 'expires' => time() + HOUR_IN_SECONDS,
        ];
        if ($issued_at !== null) {
            $data['issued_at'] = $issued_at;
        }
        $context = base64_encode(wp_json_encode($data));

        return ['capture_context' => $context, 'capture_signature' => hash_hmac('sha256', $context, 'lifecycle-test-secret')];
    }

    private function capture_params($context, $changes = [])
    {
        $token = IncompleteOrders::issue_capture_token($this->capture_request($context))->get_data();

        return array_replace($context + [
            'anonymous_id' => $token['anonymous_id'], 'checkout_id' => $token['checkout_id'],
            'session_id' => $token['session_id'], 'capture_token' => $token['token'],
            'capture_sequence' => 1, 'customer' => $this->snapshot()['customer'],
        ], $changes);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return WP_REST_Request&MockInterface
     */
    private function capture_request($params)
    {
        /** @var WP_REST_Request&MockInterface $request */
        $request = Mockery::mock(WP_REST_Request::class);
        $request->shouldReceive('get_json_params')->andReturn($params);
        $request->shouldReceive('get_header')->with('Origin')->andReturn('https://woo.test');

        return $request;
    }
}

/**
 * Deliberately limited to lifecycle SQL; unexpected queries fail instead of passing silently.
 */
class IncompleteOrderLifecycleWpdb
{
    public $prefix = 'wp_';

    public $rows = [];

    public $operations = [];

    public $failures = [];

    private $transaction;

    private $next_id = 0;

    public function prepare($sql, ...$args)
    {
        return (object) ['sql' => $sql, 'args' => $args];
    }

    public function get_var($query)
    {
        if (preg_match('/^SELECT (GET_LOCK|RELEASE_LOCK)\(/', $query->sql, $match)) {
            $this->operations[] = $match[1];

            return 1;
        }
        if (strpos($query->sql, 'SELECT customer_data FROM ') === 0) {
            $row = $this->get_row($query, ARRAY_A);

            return $row['customer_data'] ?? null;
        }
        throw new RuntimeException('Unexpected get_var: ' . $query->sql);
    }

    public function get_row($query, $format)
    {
        if (!preg_match('/^SELECT (.+) FROM (\w+) WHERE checkout_id = %s$/', $query->sql, $match)) {
            throw new RuntimeException('Unexpected get_row: ' . $query->sql);
        }
        foreach ($this->rows[$match[2]] ?? [] as $row) {
            if ($row['checkout_id'] === $query->args[0]) {
                return array_intersect_key($row, array_flip(explode(', ', $match[1])));
            }
        }

        return null;
    }

    public function query($sql)
    {
        $this->operations[] = $sql;
        if ($sql === 'START TRANSACTION') {
            $this->transaction = $this->rows;
        } elseif ($sql === 'ROLLBACK') {
            $this->rows = $this->transaction;
            $this->transaction = null;
        } elseif ($sql === 'COMMIT') {
            $this->transaction = null;
        } else {
            throw new RuntimeException('Unexpected query: ' . (is_object($sql) ? $sql->sql : $sql));
        }

        return 1;
    }

    public function insert($table, $row, $format = [])
    {
        if ($this->fails('insert', $table)) {
            return false;
        }
        foreach ($this->rows[$table] ?? [] as $existing) {
            foreach (['checkout_id', 'event_id'] as $key) {
                if (isset($row[$key]) && $existing[$key] === $row[$key]) {
                    return false;
                }
            }
        }
        $id = ++$this->next_id;
        $this->rows[$table][$id] = $row + ['id' => $id, 'order_id' => 0];

        return 1;
    }

    public function update($table, $values, $where)
    {
        if ($this->fails('update', $table)) {
            return false;
        }
        $count = 0;
        foreach ($this->rows[$table] ?? [] as $id => $row) {
            if (array_intersect_key($row, $where) == $where) {
                $this->rows[$table][$id] = array_replace($row, $values);
                $count++;
            }
        }

        return $count;
    }

    public function delete($table, $where)
    {
        if ($this->fails('delete', $table)) {
            return false;
        }
        $count = 0;
        foreach ($this->rows[$table] ?? [] as $id => $row) {
            if (array_intersect_key($row, $where) == $where) {
                unset($this->rows[$table][$id]);
                $count++;
            }
        }

        return $count;
    }

    private function fails($operation, $table)
    {
        $key = $operation . ':' . $table;
        $this->operations[] = $key;
        if (!array_key_exists($key, $this->failures)) {
            return false;
        }
        $failure = $this->failures[$key];
        unset($this->failures[$key]);
        if ($failure instanceof Throwable) {
            throw $failure;
        }

        return true;
    }
}

class IncompleteOrderLifecycleResponse
{
    private $data;

    private $status;

    private $headers = [];

    public function __construct($data = null, $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }

    public function get_data()
    {
        return $this->data;
    }

    public function get_status()
    {
        return $this->status;
    }

    public function header($name, $value)
    {
        $this->headers[$name] = $value;
    }

    public function get_headers()
    {
        return $this->headers;
    }
}

class IncompleteOrderLifecycleRedirect extends RuntimeException {}
