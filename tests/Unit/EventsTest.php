<?php

/**
 * Event payload sanitization and ULID unit tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Events;
use Adoology\Tests\TestCase;
use ReflectionMethod;

use function Brain\Monkey\Functions\when;

/**
 * @coversDefaultClass \Adoology\Events
 */
class EventsTest extends TestCase
{
    /**
     * @covers ::ulid
     */
    public function test_ulid_format()
    {
        $ulid = $this->invoke('ulid');

        $this->assertSame(26, strlen($ulid));
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid);
    }

    /**
     * @covers ::ulid
     */
    public function test_ulid_is_unique_and_monotonic_prefix()
    {
        $first = $this->invoke('ulid');
        $second = $this->invoke('ulid');

        $this->assertNotSame($first, $second);
        // Time component occupies the first ten characters.
        $this->assertSame(substr($first, 0, 6), substr($second, 0, 6));
    }

    /**
     * @covers ::sanitize_value
     */
    public function test_sanitize_value_caps_depth()
    {
        $deep = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'too deep']]]]]];

        $result = $this->invoke('sanitize_value', $deep, 0);

        $this->assertSame(['e' => null], $result['a']['b']['c']['d']);
    }

    /**
     * @covers ::sanitize_value
     */
    public function test_sanitize_value_normalizes_keys()
    {
        $result = $this->invoke('sanitize_value', ['Bad Key!' => 'value'], 0);

        $this->assertSame(['badkey' => 'value'], $result);
    }

    /**
     * @covers ::sanitize_value
     */
    public function test_sanitize_value_caps_array_size_and_string_length()
    {
        $many = array_fill(0, 60, 'x');
        $this->assertCount(50, $this->invoke('sanitize_value', $many, 0));

        $long = str_repeat('a', 2000);
        $this->assertSame(1000, strlen($this->invoke('sanitize_value', $long, 0)));
    }

    /**
     * @covers ::sanitize_value
     */
    public function test_sanitize_value_preserves_scalars()
    {
        $this->assertTrue($this->invoke('sanitize_value', true, 0));
        $this->assertSame(7, $this->invoke('sanitize_value', 7, 0));
        $this->assertNull($this->invoke('sanitize_value', null, 0));
        $this->assertSame(1.5, $this->invoke('sanitize_value', 1.5, 0));
    }

    /**
     * @covers ::schedule_processing
     */
    public function test_continuations_use_unique_action_arguments()
    {
        /** @var list<array<mixed>> $scheduled */
        $scheduled = [];
        /** @var array<string, mixed> $options */
        $options = [];
        $sequence = 0;
        when('get_option')->alias(function ($name, $default = false) use (&$options) {
            return array_key_exists($name, $options) ? $options[$name] : $default;
        });
        when('add_option')->alias(function ($name, $value) use (&$options) {
            if (array_key_exists($name, $options)) {
                return false;
            }
            $options[$name] = $value;

            return true;
        });
        when('update_option')->alias(function ($name, $value) use (&$options) {
            $options[$name] = $value;

            return true;
        });
        when('delete_option')->alias(function ($name) use (&$options) {
            unset($options[$name]);

            return true;
        });
        when('wp_generate_uuid4')->alias(function () use (&$sequence) {
            $sequence++;

            return 'uuid-' . $sequence;
        });
        when('wp_next_scheduled')->alias(function ($hook, $args) use (&$scheduled) {
            return in_array($args, $scheduled, true) ? time() + 10 : false;
        });
        when('wp_schedule_single_event')->alias(function ($timestamp, $hook, $args) use (&$scheduled) {
            $scheduled[] = $args;

            return true;
        });

        $run_at = time() + 10;
        Events::schedule_processing($run_at, true);
        Events::schedule_processing($run_at, true);

        $this->assertSame([
            ['continuation', 'uuid-1'],
        ], $scheduled);
    }

    private function invoke($method, ...$args)
    {
        $reflection = new ReflectionMethod(Events::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }
}
