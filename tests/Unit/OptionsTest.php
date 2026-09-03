<?php

/**
 * Options unit tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Options;
use Adoology\Tests\TestCase;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

/**
 * @coversDefaultClass \Adoology\Options
 */
class OptionsTest extends TestCase
{
    /**
     * @covers ::update
     */
    public function test_update_uses_add_option_when_missing()
    {
        $options = [];
        when('get_option')->alias(function ($name, $default = false) use (&$options) {
            return array_key_exists($name, $options) ? $options[$name] : $default;
        });
        expect('add_option')->once()->with('adoology_api_token', 'v', '', false)->andReturn(true);
        expect('update_option')->never();

        $this->assertTrue(Options::update('adoology_api_token', 'v'));
    }

    /**
     * @covers ::update
     */
    public function test_update_uses_update_option_when_present()
    {
        when('get_option')->justReturn('existing');
        expect('update_option')->once()->with('adoology_api_token', 'v2', false)->andReturn(true);
        expect('add_option')->never();

        $this->assertTrue(Options::update('adoology_api_token', 'v2'));
    }

    /**
     * @covers ::install_defaults
     */
    public function test_install_defaults_adds_missing_and_removes_legacy()
    {
        $options = ['adoology_inbound_secret' => 'stale'];
        when('get_option')->alias(function ($name, $default = false) use (&$options) {
            return array_key_exists($name, $options) ? $options[$name] : $default;
        });
        when('add_option')->alias(function ($name, $value) use (&$options) {
            $options[$name] = $value;

            return true;
        });
        when('delete_option')->alias(function ($name) use (&$options) {
            unset($options[$name]);

            return true;
        });

        Options::install_defaults();

        $this->assertSame('https://api.adoology.com', $options['adoology_api_base_url']);
        $this->assertSame('yes', $options['adoology_tracking_enabled']);
        $this->assertSame(30, $options['adoology_incomplete_timeout_minutes']);
        $this->assertArrayNotHasKey('adoology_inbound_secret', $options);
    }

    /**
     * @covers ::names
     */
    public function test_names_covers_core_options()
    {
        $names = Options::names();

        $this->assertContains('adoology_api_token', $names);
        $this->assertContains('adoology_webhook_secret', $names);
        $this->assertContains('adoology_connection_id', $names);
    }
}
