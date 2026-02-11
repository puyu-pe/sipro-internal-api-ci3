<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Http;

use PuyuPe\SiproInternalApiCore\Contracts\TenantAdapterInterface;
use PuyuPe\SiproInternalApiCore\Dto\ActivateTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\CreateTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\SuspendTenantRequest;
use PuyuPe\SiproInternalApiCore\Dto\WarnTenantRequest;
use PuyuPe\SiproInternalApiCore\Errors\ErrorFactory;
use PuyuPe\SiproInternalApiCore\Errors\ErrorResponse;

final class InternalApiDispatcher
{
    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function dispatch(string $method, string $path, string $rawBody, array $headers, TenantAdapterInterface $adapter): array
    {
        if (strtoupper($method) !== 'POST') {
            return [
                'status' => 405,
                'body' => ErrorFactory::validationError('Method not allowed.')->toArray(),
            ];
        }

        if ($path === '/internal/v1/tenants') {
            return $this->dispatchCreateTenant($rawBody, $adapter);
        }

        if (preg_match('#^/internal/v1/tenants/([^/]+):(warn|suspend|activate)$#', $path, $matches) !== 1) {
            return [
                'status' => 404,
                'body' => $this->routeNotFoundError()->toArray(),
            ];
        }

        $tenantUuid = $matches[1];
        $action = $matches[2];

        return $this->dispatchTenantAction($tenantUuid, $action, $rawBody, $adapter);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function dispatchCreateTenant(string $rawBody, TenantAdapterInterface $adapter): array
    {
        $payload = $this->decodeJson($rawBody);
        if ($payload === null) {
            return [
                'status' => 400,
                'body' => ErrorFactory::validationError('Invalid JSON body.')->toArray(),
            ];
        }

        try {
            $dto = CreateTenantRequest::fromArray($payload);
            $dto->validate();
        } catch (\Throwable $exception) {
            return [
                'status' => 400,
                'body' => ErrorFactory::validationError($exception->getMessage())->toArray(),
            ];
        }

        $result = $adapter->createTenant($dto);

        return [
            'status' => 200,
            'body' => array_merge(['ok' => true, 'status' => 'created'], $result),
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function dispatchTenantAction(string $tenantUuid, string $action, string $rawBody, TenantAdapterInterface $adapter): array
    {
        $payload = $this->decodeJson($rawBody);
        if ($payload === null) {
            return [
                'status' => 400,
                'body' => ErrorFactory::validationError('Invalid JSON body.')->toArray(),
            ];
        }

        try {
            switch ($action) {
                case 'warn':
                    $dto = WarnTenantRequest::fromArray($payload);
                    $dto->validate();
                    $adapter->warnTenant($tenantUuid, $dto);
                    return [
                        'status' => 200,
                        'body' => ['ok' => true, 'tenant_uuid' => $tenantUuid, 'status' => 'warn'],
                    ];
                case 'suspend':
                    $dto = SuspendTenantRequest::fromArray($payload);
                    $dto->validate();
                    $adapter->suspendTenant($tenantUuid, $dto);
                    return [
                        'status' => 200,
                        'body' => ['ok' => true, 'tenant_uuid' => $tenantUuid, 'status' => 'suspended'],
                    ];
                case 'activate':
                    $dto = ActivateTenantRequest::fromArray($payload);
                    $dto->validate();
                    $adapter->activateTenant($tenantUuid, $dto);
                    return [
                        'status' => 200,
                        'body' => ['ok' => true, 'tenant_uuid' => $tenantUuid, 'status' => 'active'],
                    ];
                default:
                    return [
                        'status' => 404,
                        'body' => $this->routeNotFoundError()->toArray(),
                    ];
            }
        } catch (\Throwable $exception) {
            return [
                'status' => 400,
                'body' => ErrorFactory::validationError($exception->getMessage())->toArray(),
            ];
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    private function decodeJson(string $rawBody): ?array
    {
        $decoded = json_decode($rawBody, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function routeNotFoundError(): ErrorResponse
    {
        if (method_exists(ErrorFactory::class, 'routeNotFound')) {
            /** @var ErrorResponse $response */
            $response = ErrorFactory::routeNotFound('Route not found.');
            return $response;
        }

        return ErrorFactory::validationError('Route not found.');
    }
}
