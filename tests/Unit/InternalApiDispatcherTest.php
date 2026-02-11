<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PuyuPe\SiproInternalApiCi3\Http\InternalApiDispatcher;
use PuyuPe\SiproInternalApiCore\Contracts\TenantAdapterInterface;
use PuyuPe\SiproInternalApiCore\Dto\ActivateTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\CreateTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\SuspendTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\WarnTenantRequest;

final class InternalApiDispatcherTest extends TestCase
{
    public function testCreateTenantHappyPathCallsAdapterAndReturnsSuccessBody(): void
    {
        $dispatcher = new InternalApiDispatcher();

        $capturedDto = null;
        $adapter = new class($capturedDto) implements TenantAdapterInterface {
            private mixed $capturedDto;

            public function __construct(&$capturedDto)
            {
                $this->capturedDto = &$capturedDto;
            }

            public function createTenant(CreateTenantRequest $dto): array
            {
                $this->capturedDto = $dto;
                return ['tenant_uuid' => 't-1', 'appkey' => 'k-1'];
            }

            public function warnTenant(string $tenantUuid, WarnTenantRequest $dto): void {}
            public function suspendTenant(string $tenantUuid, SuspendTenantRequest $dto): void {}
            public function activateTenant(string $tenantUuid, ActivateTenantRequest $dto): void {}
        };

        $result = $dispatcher->dispatch('POST', '/internal/v1/tenants', '{"name":"acme"}', [], $adapter);

        self::assertInstanceOf(CreateTenantRequest::class, $capturedDto);
        self::assertSame('acme', $capturedDto->data['name']);
        self::assertSame(200, $result['status']);
        self::assertSame(true, $result['body']['ok']);
        self::assertSame('created', $result['body']['status']);
        self::assertSame('k-1', $result['body']['appkey']);
    }

    public function testActionRouteReturnsExpectedStatusWord(): void
    {
        $dispatcher = new InternalApiDispatcher();
        $adapter = new class() implements TenantAdapterInterface {
            public function createTenant(CreateTenantRequest $dto): array { return []; }
            public function warnTenant(string $tenantUuid, WarnTenantRequest $dto): void {}
            public function suspendTenant(string $tenantUuid, SuspendTenantRequest $dto): void {}
            public function activateTenant(string $tenantUuid, ActivateTenantRequest $dto): void {}
        };

        $result = $dispatcher->dispatch('POST', '/internal/v1/tenants/uuid-1:suspend', '{"reason":"policy"}', [], $adapter);

        self::assertSame(200, $result['status']);
        self::assertSame('uuid-1', $result['body']['tenant_uuid']);
        self::assertSame('suspended', $result['body']['status']);
    }

    public function testInvalidJsonReturnsValidationError(): void
    {
        $dispatcher = new InternalApiDispatcher();
        $adapter = new class() implements TenantAdapterInterface {
            public function createTenant(CreateTenantRequest $dto): array { return []; }
            public function warnTenant(string $tenantUuid, WarnTenantRequest $dto): void {}
            public function suspendTenant(string $tenantUuid, SuspendTenantRequest $dto): void {}
            public function activateTenant(string $tenantUuid, ActivateTenantRequest $dto): void {}
        };

        $result = $dispatcher->dispatch('POST', '/internal/v1/tenants', '{', [], $adapter);

        self::assertSame(400, $result['status']);
        self::assertSame(false, $result['body']['ok']);
        self::assertSame('VALIDATION_ERROR', $result['body']['code']);
    }
}
