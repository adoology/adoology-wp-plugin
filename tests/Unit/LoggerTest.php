<?php

/**
 * Logger redaction unit tests.
 */

namespace Adoology\Tests\Unit;

use Adoology\Logger;
use Adoology\Tests\TestCase;

/**
 * @coversDefaultClass \Adoology\Logger
 */
class LoggerTest extends TestCase
{
    /**
     * @covers ::redact_string
     */
    public function test_redact_string_masks_bearer_tokens()
    {
        $this->assertSame('Bearer [redacted]', Logger::redact_string('Bearer dc_abc123.xyz'));
    }

    /**
     * @covers ::redact_string
     */
    public function test_redact_string_masks_woo_key_prefixes()
    {
        $this->assertSame('key [redacted] stored', Logger::redact_string('key ck_deadbeef1234 stored'));
        $this->assertSame('[redacted] [redacted]', Logger::redact_string('dc_one cs_two'));
    }

    /**
     * @covers ::redact_string
     */
    public function test_redact_string_masks_key_value_pairs()
    {
        $this->assertSame('token=[redacted]', Logger::redact_string('token=supersecret'));
        $this->assertSame('"api_key": [redacted]', Logger::redact_string('"api_key": "supersecret"'));
    }

    /**
     * @covers ::redact
     */
    public function test_redact_replaces_sensitive_keys()
    {
        $input = [
            'token' => 'hidden',
            'safe' => 'kept',
            'nested' => ['consumer_key' => 'hidden'],
            'signature' => 'hidden',
        ];

        $result = Logger::redact($input);

        $this->assertSame('[redacted]', $result['token']);
        $this->assertSame('kept', $result['safe']);
        $this->assertSame('[redacted]', $result['nested']['consumer_key']);
        $this->assertSame('[redacted]', $result['signature']);
    }

    /**
     * @covers ::redact
     */
    public function test_redact_replaces_objects()
    {
        $this->assertSame('[object]', Logger::redact((object) ['a' => 1]));
    }

    /**
     * @covers ::redact
     */
    public function test_redact_passes_through_scalars()
    {
        $this->assertSame(42, Logger::redact(42, 'count'));
        $this->assertTrue(Logger::redact(true, 'flag'));
    }
}
