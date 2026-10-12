<?php
require dirname(__DIR__, 2) . '/vendor/autoload.php';
require __DIR__ . '/database.php';
$store = new \PuyuPe\SiproInternalApiCi3\Nonce\DbNonceStore(sipro_test_database());
while (microtime(true) < (float) $argv[2]) { usleep(1000); }
print $store->consume($argv[1], 30) ? '1' : '0';
