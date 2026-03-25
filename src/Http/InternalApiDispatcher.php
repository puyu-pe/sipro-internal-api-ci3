<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Http;

use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantLifecycleAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantProvisioningAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionPayloadDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleResponseDTO;
use PuyuPe\SiproInternalApiCore\Errors\ErrorFactory;
use PuyuPe\SiproInternalApiCore\Errors\ErrorResponse;

final class InternalApiDispatcher
{
    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    public function dispatch(
        string $method,
        string $path,
        string $rawBody,
        array $headers,
        TenantProvisioningAdapterInterface $provisioningAdapter,
        TenantLifecycleAdapterInterface $lifecycleAdapter
    ): array
    {
        if (strtoupper($method) !== 'POST') {
            return [
                'status' => 405,
                'body' => ErrorFactory::validationError('Method not allowed.')->toArray(),
            ];
        }

        if ($path === '/internal/v1/tenants') {
            return $this->dispatchCreateTenant($rawBody, $provisioningAdapter);
        }

        if (preg_match('#^/internal/v1/tenants/([^/]+):(warn|suspend|activate)$#', $path, $matches) !== 1) {
            return [
                'status' => 404,
                'body' => $this->routeNotFoundError()->toArray(),
            ];
        }

        $appKey = $matches[1];
        $action = $matches[2];

        return $this->dispatchTenantAction($appKey, $action, $rawBody, $lifecycleAdapter);
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function dispatchCreateTenant(string $rawBody, TenantProvisioningAdapterInterface $adapter): array
    {
        $payload = $this->decodeJson($rawBody);
        if ($payload === null) {
            return [
                'status' => 400,
                'body' => ErrorFactory::validationError('Invalid JSON body.')->toArray(),
            ];
        }

        $dto = ProvisionPayloadDTO::fromArray($payload);
        $result = $adapter->createTenant($dto);

        return [
            'status' => 200,
            'body' => array_merge(['ok' => true], $result->toArray()),
        ];
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function dispatchTenantAction(string $appKey, string $action, string $rawBody, TenantLifecycleAdapterInterface $adapter): array
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
                    $dto = TenantLifecycleRequestDTO::fromArray($payload);
                    $result = $adapter->warnTenant($appKey, $dto);
                    return [
                        'status' => 200,
                        'body' => array_merge(['ok' => true], $this->responseToArray($result)),
                    ];
                case 'suspend':
                    $dto = TenantLifecycleRequestDTO::fromArray($payload);
                    $result = $adapter->suspendTenant($appKey, $dto);
                    return [
                        'status' => 200,
                        'body' => array_merge(['ok' => true], $this->responseToArray($result)),
                    ];
                case 'activate':
                    $dto = TenantLifecycleRequestDTO::fromArray($payload);
                    $result = $adapter->activateTenant($appKey, $dto);
                    return [
                        'status' => 200,
                        'body' => array_merge(['ok' => true], $this->responseToArray($result)),
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

    /**
     * @return array<string, mixed>
     */
    private function responseToArray(TenantLifecycleResponseDTO $response): array
    {
        return $response->toArray();
    }
}
