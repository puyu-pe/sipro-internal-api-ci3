<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Nonce;

use PuyuPe\SiproInternalApiCore\Contracts\NonceStoreInterface;

final class DbNonceStore implements NonceStoreInterface
{
    private const TABLE = 'internal_api_nonces';

    /** @var object */
    private $db;

    /**
     * @param object $db CI3 DB instance
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    public function has(string $nonceKey): bool
    {
        $query = $this->db
            ->select('nonce_key')
            ->from(self::TABLE)
            ->where('nonce_key', $nonceKey)
            ->limit(1)
            ->get();

        return $query->num_rows() > 0;
    }

    public function put(string $nonceKey, int $expiresAt): void
    {
        $this->db->insert(self::TABLE, [
            'nonce_key' => $nonceKey,
            'expires_at' => $expiresAt,
        ]);
    }
}
