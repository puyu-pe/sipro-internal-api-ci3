<?php

// Load the real CI3 database driver without booting an unrelated application.
require_once __DIR__ . '/framework.php';
if (!defined('BASEPATH')) { define('BASEPATH', sipro_test_framework_root() . '/system/'); }
if (!defined('APPPATH')) { define('APPPATH', __DIR__ . '/application/'); }
if (!defined('ENVIRONMENT')) { define('ENVIRONMENT', 'testing'); }
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'database/DB.php';

function sipro_test_database(): object
{
    return DB(['hostname' => getenv('SIPRO_TEST_DB_HOST'), 'username' => 'root', 'password' => '',
        'database' => 'sipro_test', 'dbdriver' => 'mysqli', 'db_debug' => false,
        'pconnect' => false, 'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci',
        'save_queries' => false, 'stricton' => true], true);
}
