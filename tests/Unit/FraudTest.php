<?php

/**
 * Fraud evaluation unit tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Fraud;
use Adoology\Tests\TestCase;

use function Brain\Monkey\Functions\when;

/**
 * @coversDefaultClass \Adoology\Fraud
 */
class FraudTest extends TestCase
{
    protected function set_up()
    {
        parent::set_up();

        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; TestRunner)';

        when('sanitize_text_field')->returnArg();
        when('wp_unslash')->returnArg();
        when('sanitize_email')->alias(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '');
        when('wp_salt')->justReturn('test-salt');
        when('get_transient')->justReturn(0);
        when('set_transient')->justReturn(true);
        when('get_option')->alias(function ($name, $default = false) {
            $values = [
                'adoology_fraud_enabled' => 'yes',
                'adoology_fraud_rate_limit' => 5,
                'adoology_duplicate_window_minutes' => 60,
                'adoology_fraud_flag_threshold' => 30,
                'adoology_fraud_hold_threshold' => 60,
                'adoology_fraud_block_threshold' => 90,
            ];

            return array_key_exists($name, $values) ? $values[$name] : $default;
        });
    }

    protected function tear_down()
    {
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        parent::tear_down();
    }

    /**
     * @covers ::evaluate
     */
    public function test_clean_submission_is_allowed()
    {
        $result = Fraud::evaluate([
            'billing_phone' => '+15551234567',
            'billing_email' => 'valid@example.com',
            'honeypot' => '',
        ], [42], false);

        $this->assertSame(0, $result['score']);
        $this->assertSame('allow', $result['action']);
        $this->assertSame([], $result['signals']);
    }

    /**
     * @covers ::evaluate
     */
    public function test_honeypot_blocks()
    {
        $result = Fraud::evaluate(['honeypot' => 'http://spam.example'], [42], false);

        $this->assertSame(100, $result['score']);
        $this->assertSame('block', $result['action']);
        $this->assertContains('honeypot', $result['signals']);
    }

    /**
     * @covers ::evaluate
     */
    public function test_bot_user_agent_flags()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'curl/8.1.2';

        $result = Fraud::evaluate([], [], false);

        $this->assertSame(35, $result['score']);
        $this->assertSame('flag', $result['action']);
        $this->assertContains('bot_user_agent', $result['signals']);
    }

    /**
     * @covers ::evaluate
     */
    public function test_invalid_phone_flags()
    {
        $result = Fraud::evaluate(['billing_phone' => '+123'], [], false);

        $this->assertSame(20, $result['score']);
        $this->assertContains('invalid_phone', $result['signals']);
    }

    /**
     * @covers ::evaluate
     */
    public function test_invalid_email_flags()
    {
        $result = Fraud::evaluate(['billing_email' => 'not-an-email'], [], false);

        $this->assertSame(20, $result['score']);
        $this->assertContains('invalid_email', $result['signals']);
    }

    /**
     * @covers ::evaluate
     */
    public function test_ip_velocity_scores_above_limit()
    {
        when('get_transient')->justReturn(10);

        $result = Fraud::evaluate([], [], false);

        $this->assertSame(40, $result['score']);
        $this->assertContains('ip_velocity', $result['signals']);
        $this->assertSame('flag', $result['action']);
    }

    /**
     * @covers ::evaluate
     */
    public function test_bot_and_invalid_phone_signals_add_up()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'python-requests/2.31';

        $result = Fraud::evaluate(['billing_phone' => '5'], [], false);

        $this->assertSame(55, $result['score']);
        $this->assertSame('flag', $result['action']);
    }

    /**
     * @covers ::evaluate
     */
    public function test_score_is_capped_at_100()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'scrapy';
        when('get_transient')->justReturn(100);

        $result = Fraud::evaluate(['honeypot' => 'x'], [], false);

        $this->assertSame(100, $result['score']);
        $this->assertSame('block', $result['action']);
    }
}
