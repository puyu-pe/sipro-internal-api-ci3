<?php
require dirname(__DIR__, 4) . '/stubs/ci3/application/config/internal_api.php';
$config['internal_api']['hmac']['keys'] = ['test' => 'secret'];
$config['internal_api']['hmac']['nonce']['enabled'] = false;
foreach (['provisioning', 'lifecycle', 'clone', 'impersonation'] as $capability) {
    $config['internal_api']['adapters'][$capability] = static fn ($ci) => $GLOBALS['sipro_fixture'];
}
$config['internal_api'] = array_replace_recursive($config['internal_api'], $GLOBALS['sipro_state']['config']);
if (isset($config['internal_api']['clone_fixture'])) {
    $fixture = $config['internal_api']['clone_fixture'];
    $root = getenv('SIPRO_CLONE_DB_ROOT');
    require_once $root . '/src/Adapters/AbstractTenantCloneAdapter.php';
    require_once $root . '/resources/contracts/v2/DatabaseCloneAdapter.php';
    $config['internal_api']['adapters']['clone'] = static function ($ci) use ($fixture) {
        $pdo = new PDO('mysql:host=' . getenv('SIPRO_TEST_DB_HOST'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return new \PuyuPe\SiproInternalApiCloneDb\Conformance\DatabaseCloneAdapter($pdo, $fixture['directory'], $fixture['databases']);
    };
}
