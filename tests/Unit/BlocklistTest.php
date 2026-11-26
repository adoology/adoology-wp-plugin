<?php

/**
 * Workspace blocklist unit tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Blocklist;
use Adoology\Fraud;
use Adoology\Tests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @coversDefaultClass \Adoology\Blocklist
 */
class BlocklistTest extends TestCase
{
    /** @var array<string, mixed> */
    private $options = [];

    protected function set_up()
    {
        parent::set_up();

        $_SERVER['REMOTE_ADDR'] = '59.153.202.48';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; TestRunner)';
        $this->options = [];

        when('sanitize_text_field')->returnArg();
        when('wp_unslash')->returnArg();
        when('__')->returnArg();
        when('sanitize_email')->returnArg();
        when('wp_salt')->justReturn('test-salt');
        when('get_transient')->justReturn(0);
        when('set_transient')->justReturn(true);
        when('get_option')->alias(fn ($name, $default = false) => array_key_exists($name, $this->options) ? $this->options[$name] : $default);
    }

    protected function tear_down()
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'], $_COOKIE['adoology_device_id']);
        parent::tear_down();
    }

    /**
     * @param  array<string, array<string, array{0: string, 1: int}>>  $index
     */
    private function storeList(array $index)
    {
        $this->options['adoology_blocklist'] = [
            'version' => '1-1',
            'fetched_at' => time(),
            'index' => array_merge(['ip' => [], 'phone' => [], 'device' => []], $index),
        ];
    }

    /**
     * @covers ::match
     */
    public function test_matches_active_phone_ip_and_device_hashes()
    {
        $device = 'b2f6a0c4-3c1e-4d55-9a4e-2f1c9b7d8e10';
        $this->storeList([
            'phone' => [hash('sha256', '+8801711311160') => ['blk_phone', 0]],
            'ip' => [hash('sha256', '59.153.202.48') => ['blk_ip', time() + 3600]],
            'device' => [hash('sha256', $device) => ['blk_device', 0]],
        ]);

        $matches = Blocklist::match('59.153.202.48', '+8801711311160', strtoupper($device));

        $this->assertSame([
            ['id' => 'blk_ip', 'type' => 'ip'],
            ['id' => 'blk_phone', 'type' => 'phone'],
            ['id' => 'blk_device', 'type' => 'device'],
        ], $matches);
    }

    /**
     * @covers ::match
     */
    public function test_ignores_expired_entries()
    {
        $this->storeList(['phone' => [hash('sha256', '+8801711311160') => ['blk_phone', time() - 1]]]);

        $this->assertSame([], Blocklist::match('', '+8801711311160', ''));
    }

    /**
     * @covers ::match
     */
    public function test_allows_everything_before_the_first_download()
    {
        $this->assertSame([], Blocklist::match('59.153.202.48', '+8801711311160', ''));
    }

    /**
     * @covers ::normalize_phone
     *
     * @dataProvider phones
     */
    public function test_normalizes_phones_to_the_same_e164_as_adoology($raw, $calling_code, $expected)
    {
        $this->assertSame($expected, Blocklist::normalize_phone($raw, 'BD', $calling_code));
    }

    public function phones()
    {
        return [
            'local with dash' => ['01711-311160', '+880', '+8801711311160'],
            'local without trunk zero' => ['1711311160', '880', '+8801711311160'],
            'country code without plus' => ['8801711311160', '+880', '+8801711311160'],
            'international prefix' => ['008801711311160', '+880', '+8801711311160'],
            'already e164 with spaces' => ['+880 1711 311160', '+1', '+8801711311160'],
            'unknown country' => ['01711311160', '', ''],
            'empty' => ['', '+880', ''],
        ];
    }

    /**
     * @covers ::canonical_ip
     */
    public function test_canonical_ip_matches_the_api_form()
    {
        $this->assertSame('2001:db8::1', Blocklist::canonical_ip('2001:DB8:0:0:0:0:0:1'));
        $this->assertSame('59.153.202.48', Blocklist::canonical_ip(' 59.153.202.48 '));
        $this->assertSame('', Blocklist::canonical_ip('not-an-ip'));
    }

    /**
     * @covers ::forwarded_client_ip
     */
    public function test_forwarded_ip_ignores_forged_leftmost_entries()
    {
        when('sanitize_text_field')->returnArg();
        when('wp_unslash')->returnArg();

        $this->assertSame('59.153.202.48', Blocklist::forwarded_client_ip('1.2.3.4, 59.153.202.48'));
        $this->assertSame('59.153.202.48', Blocklist::forwarded_client_ip('1.2.3.4, 59.153.202.48, 10.0.0.5'));
        $this->assertSame('10.0.0.5', Blocklist::forwarded_client_ip('10.0.0.5'));
        $this->assertSame('', Blocklist::forwarded_client_ip(''));
    }

    /**
     * @covers ::client_ip
     */
    public function test_reads_the_cloudflare_header_only_when_configured()
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '103.4.145.2';

        $this->assertSame('59.153.202.48', Blocklist::client_ip());

        $this->options['adoology_ip_source'] = 'cf-connecting-ip';
        $this->assertSame('103.4.145.2', Blocklist::client_ip());

        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    /**
     * @covers \Adoology\Fraud::evaluate
     */
    public function test_checkout_from_a_blocked_phone_is_refused_regardless_of_score()
    {
        $this->options['adoology_fraud_block_threshold'] = 100;
        $this->storeList(['phone' => [hash('sha256', '+8801711311160') => ['blk_phone', 0]]]);

        $result = Fraud::evaluate(['billing_phone' => '+880 1711-311160'], [42], false);

        $this->assertSame('block', $result['action']);
        $this->assertSame(['blocklist_phone'], $result['signals']);
    }

    /**
     * @covers \Adoology\Fraud::evaluate
     */
    public function test_blocklist_can_be_turned_off()
    {
        $this->options['adoology_blocklist_enabled'] = 'no';
        $this->storeList(['phone' => [hash('sha256', '+8801711311160') => ['blk_phone', 0]]]);

        $result = Fraud::evaluate(['billing_phone' => '+880 1711-311160'], [42], false);

        $this->assertNotContains('blocklist_phone', $result['signals']);
    }
}
