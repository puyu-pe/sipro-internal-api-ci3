<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Adapters;

use PuyuPe\SiproInternalApiCore\Contracts\Adapter\ProvisioningSupport;
use PuyuPe\SiproInternalApiCore\Errors\{ErrorFactory, InternalApiException};

abstract class AbstractTenantProvisioningAdapter
{
    use ProvisioningSupport;

    public function __construct(protected readonly object $db) {}
    protected function environmentSuffix(): string { return defined('ENVIRONMENT') && ENVIRONMENT === 'production' ? 'prod' : 'dev'; }

    protected function createDatabaseIfMissing(string $dbName): void
    {
        if (!$this->db->query('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')) {
            throw new InternalApiException(ErrorFactory::dbCreateFailed('Failed to create tenant database.'));
        }
    }

    protected function upsertSystemParameter(string $code, string $value): void
    {
        $query = $this->db->where(['code' => $code, 'level' => 'sistema'])->get('system_parameter');
        if (!$query) { throw new InternalApiException(ErrorFactory::provisionFailed('Failed to read system parameter.')); }
        $data = ['parameter_value' => $value, 'description' => $code];
        $ok = $query->num_rows() > 0
            ? $this->db->where(['code' => $code, 'level' => 'sistema'])->update('system_parameter', $data)
            : $this->db->insert('system_parameter', $data + ['code' => $code, 'level' => 'sistema']);
        if (!$ok) { throw new InternalApiException(ErrorFactory::provisionFailed('Failed to update system parameter.')); }
    }

    protected function switchDatabase(string $dbName): void
    {
        if (!$this->db->db_select($dbName)) {
            throw new InternalApiException(ErrorFactory::provisionFailed('Failed to switch tenant database.'));
        }
    }

    protected function restoreDatabase(string $dbName): void { $this->switchDatabase($dbName); }

    protected function usingDatabase(string $dbName, callable $operation): mixed
    {
        $previous = $this->db->database;
        try {
            $this->switchDatabase($dbName);
            return $operation();
        } finally {
            $this->restoreDatabase($previous);
        }
    }
}
