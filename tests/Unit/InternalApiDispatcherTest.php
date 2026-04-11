<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PuyuPe\SiproInternalApiCi3\Http\InternalApiDispatcher;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantLifecycleAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantProvisioningAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionPayloadDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionResponseDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleResponseDTO;

final class InternalApiDispatcherTest extends TestCase
{
    private InternalApiDispatcher $dispatcher;
    private TenantProvisioningAdapterInterface $provisioningAdapter;
    private TenantLifecycleAdapterInterface $lifecycleAdapter;

    protected function setUp(): void
    {
        $this->dispatcher = new InternalApiDispatcher();

        $this->provisioningAdapter = new class implements TenantProvisioningAdapterInterface {
            public ?ProvisionPayloadDTO $lastRequest = null;

            public function createTenant(ProvisionPayloadDTO $dto): ProvisionResponseDTO
            {
                $this->lastRequest = $dto;

                return new ProvisionResponseDTO(
                    appKey: $dto->project->appKey ?? 'fake-key',
                    projectCode: $dto->project->code,
                    database: 'db_fake',
                    status: 'created',
                    provisionedAt: '2026-01-01T00:00:00Z',
                    dbHost: null,
                    migrated: true,
                    seeded: true,
                    systemParametersUpdated: true,
                    usersCreated: 1,
                    executionTimeMs: 10,
                    warnings: []
                );
            }
        };

        $this->lifecycleAdapter = new class implements TenantLifecycleAdapterInterface {
            public ?string $lastAction = null;
            public ?TenantLifecycleRequestDTO $lastRequest = null;

            public function warnTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
            {
                $this->lastAction = 'warn';
                $this->lastRequest = $dto;
                return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'ok', 'warned');
            }

            public function suspendTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
            {
                $this->lastAction = 'suspend';
                $this->lastRequest = $dto;
                return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'ok', 'suspended');
            }

            public function activateTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
            {
                $this->lastAction = 'activate';
                $this->lastRequest = $dto;
                return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'ok', 'active');
            }

            public function closeTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
            {
                $this->lastAction = 'close';
                $this->lastRequest = $dto;
                return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'ok', 'closed');
            }

            public function reopenTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO
            {
                $this->lastAction = 'reopen';
                $this->lastRequest = $dto;
                return new TenantLifecycleResponseDTO($appKey, $dto->projectCode, 'ok', 'active');
            }
        };
    }

    public function testRejectsNonPostMethod(): void
    {
        $result = $this->dispatcher->dispatch('GET', '/internal/v1/tenants', '{}', [], $this->provisioningAdapter, $this->lifecycleAdapter);

        self::assertSame(405, $result['status']);
    }

    public function testUnknownRouteReturns404(): void
    {
        $result = $this->dispatcher->dispatch('POST', '/internal/v1/unknown', '{}', [], $this->provisioningAdapter, $this->lifecycleAdapter);

        self::assertSame(404, $result['status']);
    }

    public function testInvalidJsonReturns400(): void
    {
        $result = $this->dispatcher->dispatch('POST', '/internal/v1/tenants', '{invalid', [], $this->provisioningAdapter, $this->lifecycleAdapter);

        self::assertSame(400, $result['status']);
        self::assertSame('VALIDATION_ERROR', $result['body']['code']);
    }

    public function testWarnTenantDispatchesCorrectly(): void
    {
        $body = json_encode(['appKey' => 'ak1', 'projectCode' => 'PROJ']);

        $result = $this->dispatcher->dispatch('POST', '/internal/v1/tenants/ak1:warn', $body, [], $this->provisioningAdapter, $this->lifecycleAdapter);

        self::assertSame(200, $result['status']);
        self::assertSame('warn', $this->lifecycleAdapter->lastAction);
        self::assertTrue($result['body']['ok']);
    }

    public function testSuspendTenantDispatchesCorrectly(): void
    {
        $body = json_encode(['appKey' => 'ak1', 'projectCode' => 'PROJ']);

        $result = $this->dispatcher->dispatch('POST', '/internal/v1/tenants/ak1:suspend', $body, [], $this->provisioningAdapter, $this->lifecycleAdapter);

        self::assertSame(200, $result['status']);
        self::assertSame('suspend', $this->lifecycleAdapter->lastAction);
    }

    public function testActivateTenantDispatchesCorrectly(): void
    {
        $body = json_encode(['appKey' => 'ak1', 'projectCode' => 'PROJ']);

        $result = $this->dispatcher->dispatch('POST', '/internal/v1/tenants/ak1:activate', $body, [], $this->provisioningAdapter, $this->lifecycleAdapter);

        self::assertSame(200, $result['status']);
        self::assertSame('activate', $this->lifecycleAdapter->lastAction);
    }

    public function testCloseTenantDispatchesCorrectly(): void
    {
        $body = json_encode(['appKey' => 'ak1', 'projectCode' => 'PROJ', 'reason' => 'CLIENT_REQUEST']);

        $result = $this->dispatcher->dispatch('POST', '/internal/v1/tenants/ak1:close', $body, [], $this->provisioningAdapter, $this->lifecycleAdapter);

        self::assertSame(200, $result['status']);
        self::assertSame('close', $this->lifecycleAdapter->lastAction);
        self::assertTrue($result['body']['ok']);
        self::assertSame('closed', $result['body']['systemStatus']);
    }

    public function testReopenTenantDispatchesCorrectly(): void
    {
        $body = json_encode(['appKey' => 'ak1', 'projectCode' => 'PROJ']);

        $result = $this->dispatcher->dispatch('POST', '/internal/v1/tenants/ak1:reopen', $body, [], $this->provisioningAdapter, $this->lifecycleAdapter);

        self::assertSame(200, $result['status']);
        self::assertSame('reopen', $this->lifecycleAdapter->lastAction);
        self::assertTrue($result['body']['ok']);
        self::assertSame('active', $result['body']['systemStatus']);
    }

    public function testCloseTenantPassesRequestedByToAdapter(): void
    {
        $body = json_encode(['appKey' => 'ak1', 'projectCode' => 'PROJ', 'requestedBy' => 'user-99']);

        $this->dispatcher->dispatch('POST', '/internal/v1/tenants/ak1:close', $body, [], $this->provisioningAdapter, $this->lifecycleAdapter);

        self::assertSame('user-99', $this->lifecycleAdapter->lastRequest?->requestedBy);
    }
}
