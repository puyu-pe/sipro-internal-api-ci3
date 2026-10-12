<?php
$active_group = 'master';
$query_builder = true;
$db['master'] = [
    'hostname' => getenv('SIPRO_TEST_DB_HOST'), 'username' => 'root', 'password' => '',
    'database' => 'sipro_test', 'dbdriver' => 'mysqli', 'db_debug' => false, 'pconnect' => false,
    'char_set' => 'utf8mb4', 'dbcollat' => 'utf8mb4_unicode_ci', 'save_queries' => false,
];
