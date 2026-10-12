<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['internal_api'] = [
    'enabled' => true,
    'hmac' => [
        'allowed_clock_skew_seconds' => 300,
        'keys' => [], // key ID => secret, loaded from your secret configuration.
        'nonce' => ['enabled' => true, 'connection' => 'master'],
    ],
    // Each factory receives the CI controller and returns the matching core adapter.
    'adapters' => [
        'provisioning' => null,
        'lifecycle' => null,
        'clone' => null,
        'impersonation' => null,
    ],
    'impersonation' => ['duration' => ['default_minutes' => 5, 'min_minutes' => 5, 'max_minutes' => 60]],
];
