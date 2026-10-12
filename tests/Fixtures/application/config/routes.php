<?php
require dirname(__DIR__, 4) . '/stubs/ci3/application/config/routes.php';
$route['default_controller'] = 'InternalApi';
$route['translate_uri_dashes'] = false;
$route['404_override'] = '';
