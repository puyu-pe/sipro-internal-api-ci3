<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------
| Internal API bridge configuration
| -------------------------------------------------------------------
| - allowed_clock_skew_seconds: configurable for core verifier if your core
|   implementation reads this value from config.
| - keys: keyId => shared secret. Prefer env vars or files outside repo.
*/
$config['internal_api'] = [
    'allowed_clock_skew_seconds' => 300,
    'nonce_enabled' => true,
    'keys' => [
        'sipro-2026-01' => 'secret',
    ],
    // 'resolve_secret_by_key_id' => static function (string $keyId): ?string {
    //     return getenv('INTERNAL_API_SECRET_' . $keyId) ?: null;
    // },
    // 'adapter_class' => 'ApplicationTenantAdapter',
];
