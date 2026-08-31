<?php
/**
 * Connection identifier unit tests.
 *
 * @package Adoology
 */

namespace Adoology\Tests\Unit;

use Adoology\Connection;

/**
 * @coversDefaultClass \Adoology\Connection
 */
class ConnectionTest extends \Adoology\Tests\TestCase {

	/**
	 * @covers ::is_valid_connection_id
	 */
	public function test_accepts_26_char_ulid_alphabet() {
		$this->assertTrue(Connection::is_valid_connection_id('01ARZ3NDEKTSV4RRFFQ69G5FAV'));
	}

	/**
	 * @covers ::is_valid_connection_id
	 */
	public function test_rejects_malformed_ids() {
		$this->assertFalse(Connection::is_valid_connection_id('01ARZ3NDEKTSV4RRFFQ69G5FA'));
		$this->assertFalse(Connection::is_valid_connection_id('01ARZ3NDEKTSV4RRFFQ69G5FAVA'));
		$this->assertFalse(Connection::is_valid_connection_id('01ARZ3NDEKTSV4RRFFQ69G5FAU'));
		$this->assertFalse(Connection::is_valid_connection_id(''));
		$this->assertFalse(Connection::is_valid_connection_id(123));
	}

	/**
	 * @covers ::is_valid_connection_id
	 */
	public function test_rejects_disallowed_characters() {
		$this->assertFalse(Connection::is_valid_connection_id('0IARZ3NDEKTSV4RRFFQ69G5FAV'));
		$this->assertFalse(Connection::is_valid_connection_id('0LARZ3NDEKTSV4RRFFQ69G5FAV'));
		$this->assertFalse(Connection::is_valid_connection_id('0*ARZ3NDEKTSV4RRFFQ69G5FAV'));
	}
}
