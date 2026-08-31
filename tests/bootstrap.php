<?php

/**
 * PHPUnit bootstrap.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}

require_once __DIR__ . '/../vendor/autoload.php';

// Initialize Patchwork before stubs are defined so Brain Monkey can
// redefine any stubbed function during tests.
require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';

require_once __DIR__ . '/stubs.php';
