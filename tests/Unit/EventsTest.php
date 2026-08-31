<?php
/**
 * Event payload sanitization and ULID unit tests.
 *
 * @package Adoology
 */

namespace Adoology\Tests\Unit;

use Adoology\Events;
use ReflectionMethod;

/**
 * @coversDefaultClass \Adoology\Events
 */
class EventsTest extends \Adoology\Tests\TestCase {

	/**
	 * @covers ::ulid
	 */
	public function test_ulid_format() {
		$ulid = $this->invoke('ulid');

		$this->assertSame(26, strlen($ulid));
		$this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid);
	}

	/**
	 * @covers ::ulid
	 */
	public function test_ulid_is_unique_and_monotonic_prefix() {
		$first  = $this->invoke('ulid');
		$second = $this->invoke('ulid');

		$this->assertNotSame($first, $second);
		// Time component occupies the first ten characters.
		$this->assertSame(substr($first, 0, 6), substr($second, 0, 6));
	}

	/**
	 * @covers ::sanitize_value
	 */
	public function test_sanitize_value_caps_depth() {
		$deep = array('a' => array('b' => array('c' => array('d' => array('e' => array('f' => 'too deep'))))));

		$result = $this->invoke('sanitize_value', $deep, 0);

		$this->assertSame(array('e' => null), $result['a']['b']['c']['d']);
	}

	/**
	 * @covers ::sanitize_value
	 */
	public function test_sanitize_value_normalizes_keys() {
		$result = $this->invoke('sanitize_value', array('Bad Key!' => 'value'), 0);

		$this->assertSame(array('badkey' => 'value'), $result);
	}

	/**
	 * @covers ::sanitize_value
	 */
	public function test_sanitize_value_caps_array_size_and_string_length() {
		$many = array_fill(0, 60, 'x');
		$this->assertCount(50, $this->invoke('sanitize_value', $many, 0));

		$long = str_repeat('a', 2000);
		$this->assertSame(1000, strlen($this->invoke('sanitize_value', $long, 0)));
	}

	/**
	 * @covers ::sanitize_value
	 */
	public function test_sanitize_value_preserves_scalars() {
		$this->assertTrue($this->invoke('sanitize_value', true, 0));
		$this->assertSame(7, $this->invoke('sanitize_value', 7, 0));
		$this->assertNull($this->invoke('sanitize_value', null, 0));
		$this->assertSame(1.5, $this->invoke('sanitize_value', 1.5, 0));
	}

	private function invoke($method, ...$args) {
		$reflection = new ReflectionMethod(Events::class, $method);
		$reflection->setAccessible(true);

		return $reflection->invokeArgs(null, $args);
	}
}
