<?php

declare(strict_types=1);

// Framework selection is test-only; consumers keep their installed CI3 distribution.
function sipro_test_framework_root(): string
{
    $package = getenv('SIPRO_CI3_FRAMEWORK') ?: 'codeigniter/framework';
    if (!in_array($package, ['codeigniter/framework', 'pocketarc/codeigniter'], true)) {
        throw new RuntimeException('Unsupported test framework package.');
    }
    return dirname(__DIR__, 2) . '/vendor/' . $package;
}
