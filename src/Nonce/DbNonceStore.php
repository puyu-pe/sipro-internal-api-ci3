<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Nonce;

use PuyuPe\SiproInternalApiCore\Security\Hmac\NonceStoreInterface;
use RuntimeException;

/** MySQL/MariaDB store, on a dedicated master connection with db_debug disabled. */
final class DbNonceStore implements NonceStoreInterface
{
    public function __construct(private readonly object $db)
    {
        if ($db->db_debug) {
            throw new RuntimeException('The nonce connection must disable db_debug.');
        }
    }

    public function consume(string $nonceKey, int $ttlSeconds): bool
    {
        if ($ttlSeconds < 1) { throw new \InvalidArgumentException('Nonce TTL must be positive.'); }
        // The unique insert elects one winner even when multiple requests race.
        if (!$this->db->query('DELETE FROM internal_api_nonces WHERE nonce_key = ? AND expires_at <= UNIX_TIMESTAMP()', [$nonceKey])) {
            throw new RuntimeException('Nonce expiration failed.');
        }
        if ($this->db->query('INSERT INTO internal_api_nonces (nonce_key, expires_at) VALUES (?, UNIX_TIMESTAMP() + ?)', [$nonceKey, $ttlSeconds])) {
            return true;
        }
        if ((int) $this->db->error()['code'] === 1062) { return false; }
        throw new RuntimeException('Nonce reservation failed.');
    }
}
