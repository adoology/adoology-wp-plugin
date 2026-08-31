<?php
/**
 * Base test case with Brain Monkey lifecycle.
 *
 * @package Adoology
 */

namespace Adoology\Tests;

use Brain\Monkey;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

abstract class TestCase extends PolyfillTestCase {

    protected function set_up() {
        parent::set_up();
        Monkey\setUp();
    }

    protected function tear_down() {
        Monkey\tearDown();
        parent::tear_down();
    }
}
