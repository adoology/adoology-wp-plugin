<?php

/**
 * Base test case with Brain Monkey lifecycle.
 */

namespace Adoology\Tests;

use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillTestCase;

use function Brain\Monkey\setUp;
use function Brain\Monkey\tearDown;

abstract class TestCase extends PolyfillTestCase
{
    protected function set_up()
    {
        parent::set_up();
        setUp();
    }

    protected function tear_down()
    {
        tearDown();
        parent::tear_down();
    }
}
