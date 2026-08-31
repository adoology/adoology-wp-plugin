<?php
/**
 * Webhook signature validation unit tests.
 *
 * @package Adoology
 */

namespace Adoology\Tests\Unit;

use Adoology\Webhooks;

/**
 * @coversDefaultClass \Adoology\Webhooks
 */
class WebhooksTest extends \Adoology\Tests\TestCase {

	const SECRET = '4c1a77c7e5b64a2ea67f2ceea2b45e2e9d1f30a8b74c6f0d2e1a88b53f9c01d2';
	const NOW    = 1000000;

	/**
	 * @covers ::is_valid_sync_signature
	 */
	public function test_accepts_fresh_correct_signature() {
		$timestamp = '000999900';
		$signature = $this->signature('product.updated', $timestamp, self::SECRET);

		$this->assertTrue(Webhooks::is_valid_sync_signature('adoology', 'product.updated', $timestamp, $signature, self::SECRET, self::NOW));
	}

	/**
	 * @covers ::is_valid_sync_signature
	 */
	public function test_rejects_wrong_source() {
		$timestamp = '000999900';
		$signature = $this->signature('product.updated', $timestamp, self::SECRET);

		$this->assertFalse(Webhooks::is_valid_sync_signature('other', 'product.updated', $timestamp, $signature, self::SECRET, self::NOW));
	}

	/**
	 * @covers ::is_valid_sync_signature
	 */
	public function test_rejects_stale_timestamp() {
		$timestamp = '000900000';
		$signature = $this->signature('product.updated', $timestamp, self::SECRET);

		$this->assertFalse(Webhooks::is_valid_sync_signature('adoology', 'product.updated', $timestamp, $signature, self::SECRET, self::NOW));
	}

	/**
	 * @covers ::is_valid_sync_signature
	 */
	public function test_rejects_wrong_secret() {
		$timestamp = '000999900';
		$signature = $this->signature('product.updated', $timestamp, 'attacker-controlled');

		$this->assertFalse(Webhooks::is_valid_sync_signature('adoology', 'product.updated', $timestamp, $signature, self::SECRET, self::NOW));
	}

	/**
	 * @covers ::is_valid_sync_signature
	 */
	public function test_rejects_empty_secret() {
		$timestamp = '000999900';
		$signature = $this->signature('product.updated', $timestamp, self::SECRET);

		$this->assertFalse(Webhooks::is_valid_sync_signature('adoology', 'product.updated', $timestamp, $signature, '', self::NOW));
	}

	/**
	 * @covers ::is_valid_sync_signature
	 */
	public function test_rejects_malformed_event_names() {
		$timestamp = '000999900';

		$this->assertFalse(Webhooks::is_valid_sync_signature('adoology', str_repeat('x', 200), $timestamp, 'sig', self::SECRET, self::NOW));
	}

	/**
	 * @covers ::event_id
	 */
	public function test_event_id_is_deterministic_sha256() {
		$expected = hash('sha256', "product.updated\n{\"id\":1}");

		$this->assertSame($expected, Webhooks::event_id('product.updated', '{"id":1}'));
		$this->assertNotSame($expected, Webhooks::event_id('product.deleted', '{"id":1}'));
	}

	private function signature($event, $timestamp, $secret) {
		return base64_encode(hash_hmac('sha256', $event . '.' . $timestamp, $secret, true));
	}
}
