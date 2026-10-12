<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PuyuPe\SiproInternalApiCi3\Nonce\DbNonceStore;
use PuyuPe\SiproInternalApiCi3\Adapters\AbstractTenantProvisioningAdapter;

final class DatabaseTest extends TestCase
{
    private object $db;
    protected function setUp(): void
    {
        if (!getenv('SIPRO_TEST_DB_HOST')) { self::markTestSkipped('Set SIPRO_TEST_DB_HOST to a disposable MariaDB instance.'); }
        require_once __DIR__ . '/../Fixtures/database.php';
        $this->db = \sipro_test_database();
        $this->db->query('CREATE TABLE IF NOT EXISTS internal_api_nonces (nonce_key CHAR(64) PRIMARY KEY, expires_at BIGINT UNSIGNED NOT NULL, INDEX (expires_at)) ENGINE=InnoDB');
    }
    protected function tearDown(): void { if (isset($this->db)) { $this->db->close(); } }

    public function testExpiredNonceCanBeReusedAndConcurrentConsumptionHasOneWinner(): void
    {
        $key = hash('sha256', random_bytes(16));
        $store = new DbNonceStore($this->db);
        self::assertTrue($store->consume($key, 10));
        self::assertFalse($store->consume($key, 10));
        $this->db->query('UPDATE internal_api_nonces SET expires_at=UNIX_TIMESTAMP()-1 WHERE nonce_key=?', [$key]);
        self::assertTrue($store->consume($key, 10));
        $raceKey = hash('sha256', random_bytes(16));
        $start = (string) (microtime(true) + 1);
        $workers = [];
        for ($i = 0; $i < 4; $i++) {
            $process = proc_open([PHP_BINARY, __DIR__ . '/../Fixtures/nonce_worker.php', $raceKey, $start], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $results = [];
        foreach ($workers as [$process, $pipes]) {
            $results[] = trim(stream_get_contents($pipes[1]));
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            self::assertSame(0, proc_close($process), $stderr);
        }
        sort($results);
        self::assertSame(['0', '0', '0', '1'], $results);
        $this->db->query('DELETE FROM internal_api_nonces WHERE nonce_key IN (?, ?)', [$key, $raceKey]);
    }

    public function testProvisioningUpsertAndConnectionRestoration(): void
    {
        $adapter = new class($this->db) extends AbstractTenantProvisioningAdapter {
            protected function databasePrefix(): string { return 'test'; }
            public function create(string $name): void { $this->createDatabaseIfMissing($name); }
            public function enter(string $name, callable $callback): mixed { return $this->usingDatabase($name, $callback); }
            public function set(string $code, string $value): void { $this->upsertSystemParameter($code, $value); }
        };
        $name = 'sipro_helper_' . bin2hex(random_bytes(5));
        try {
            $adapter->create($name);
            try {
                $adapter->enter($name, function () use ($adapter): void {
                    $this->db->query('CREATE TABLE system_parameter (code VARCHAR(30), level VARCHAR(30), parameter_value TEXT, description TEXT, PRIMARY KEY(code, level))');
                    $adapter->set('mode', 'same');
                    $adapter->set('mode', 'same');
                    self::assertSame(1, (int) $this->db->query('SELECT COUNT(*) AS n FROM system_parameter')->row()->n);
                    throw new \RuntimeException('operation failed');
                });
            } catch (\RuntimeException $error) { self::assertSame('operation failed', $error->getMessage()); }
            self::assertSame('sipro_test', $this->db->query('SELECT DATABASE() AS name')->row()->name);
        } finally { $this->db->query("DROP DATABASE IF EXISTS `$name`"); }
    }
}
