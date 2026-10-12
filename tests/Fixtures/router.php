<?php

// Real CI3 bootstrap behind PHP's HTTP server. No production application is used.
require_once __DIR__ . '/framework.php';
define('ENVIRONMENT', 'testing');
define('FCPATH', __DIR__ . '/');
define('SELF', 'index.php');
define('APPPATH', __DIR__ . '/application/');
define('VIEWPATH', APPPATH . 'views/');
define('BASEPATH', sipro_test_framework_root() . '/system/');
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$contracts = dirname((new ReflectionClass(\PuyuPe\SiproInternalApiCore\Http\InternalApi::class))->getFileName(), 3) . '/resources/contracts/v2/';
require $contracts . 'RecordingAdapter.php';
$GLOBALS['sipro_fixture'] = new \PuyuPe\SiproInternalApiCore\Conformance\RecordingAdapter();
$GLOBALS['sipro_state'] = json_decode(file_get_contents(getenv('SIPRO_TEST_STATE')), true);
$failure = $GLOBALS['sipro_state']['failure'] ?? null;
if ($failure) {
    $GLOBALS['sipro_fixture']->failure = isset($failure['code'])
        ? new \PuyuPe\SiproInternalApiCore\Errors\InternalApiException(new \PuyuPe\SiproInternalApiCore\Errors\InternalApiError(\PuyuPe\SiproInternalApiCore\Errors\ErrorCode::from($failure['code']), $failure['message'], $failure['details']))
        : new RuntimeException($failure['message']);
}
register_shutdown_function(static function (): void { file_put_contents(getenv('SIPRO_TEST_STATE') . '.result', (string) count($GLOBALS['sipro_fixture']->calls)); });
require BASEPATH . 'core/CodeIgniter.php';
