<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/adoology-connector.php',
        __DIR__ . '/uninstall.php',
    ])
    ->withPhpSets(php74: true)
    ->withImportNames(removeUnusedImports: true)
    ->withSkip([
        __DIR__ . '/tests/stubs.php',
    ]);
