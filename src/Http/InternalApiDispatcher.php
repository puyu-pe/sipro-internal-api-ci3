<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Http;

use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantImpersonationAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantLifecycleAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantProvisioningAdapterInterface;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ImpersonableUserSearchRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ImpersonationRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\ProvisionPayloadDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleRequestDTO;
use PuyuPe\SiproInternalApiCore\Contracts\Dto\TenantLifecycleResponseDTO;
use PuyuPe\SiproInternalApiCore\Errors\ErrorCode;
use PuyuPe\SiproInternalApiCore\Errors\InternalApiError;

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
        TenantLifecycleAdapterInterface $lifecycleAdapter,
        ?TenantImpersonationAdapterInterface $impersonationAdapter = null
    ): array {
        if (strtoupper($method) !== 'POST') {
            return [
                'status' => 405,
                'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, 'Method not allowed.'),
            ];
        }

        if ($path === '/internal/v1/tenants') {
            return $this->dispatchCreateTenant($rawBody, $provisioningAdapter);
        }

        // Impersonation routes
        if (preg_match('#^/internal/v1/tenants/([^/]+):impersonation-users/search$#', $path, $matches) === 1) {
            if ($impersonationAdapter === null) {
                return [
                    'status' => 501,
                    'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, 'Impersonation not implemented.'),
                ];
            }
            return $this->dispatchSearchImpersonableUsers($matches[1], $rawBody, $impersonationAdapter);
        }

        if (preg_match('#^/internal/v1/tenants/([^/]+):impersonate$#', $path, $matches) === 1) {
            if ($impersonationAdapter === null) {
                return [
                    'status' => 501,
                    'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, 'Impersonation not implemented.'),
                ];
            }
            return $this->dispatchImpersonateUser($matches[1], $rawBody, $impersonationAdapter);
        }

        if (preg_match('#^/internal/v1/tenants/([^/]+):(warn|suspend|activate|close|reopen)$#', $path, $matches) !== 1) {
            return [
                'status' => 404,
                'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, 'Route not found.'),
            ];
        }

        $resolveKey = $matches[1];
        $action = $matches[2];

        return $this->dispatchTenantAction($resolveKey, $action, $rawBody, $lifecycleAdapter);
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
                'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, 'Invalid JSON body.'),
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
    private function dispatchTenantAction(string $resolveKey, string $action, string $rawBody, TenantLifecycleAdapterInterface $adapter): array
    {
        $payload = $this->decodeJson($rawBody);
        if ($payload === null) {
            return [
                'status' => 400,
                'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, 'Invalid JSON body.'),
            ];
        }

        try {
            $dto = TenantLifecycleRequestDTO::fromArray($payload);

            $result = match ($action) {
                'warn'     => $adapter->warnTenant($resolveKey, $dto),
                'suspend'  => $adapter->suspendTenant($resolveKey, $dto),
                'activate' => $adapter->activateTenant($resolveKey, $dto),
                'close'    => $adapter->closeTenant($resolveKey, $dto),
                'reopen'   => $adapter->reopenTenant($resolveKey, $dto),
                default    => null,
            };

            if ($result === null) {
                return [
                    'status' => 404,
                    'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, 'Route not found.'),
                ];
            }

            return [
                'status' => 200,
                'body' => array_merge(['ok' => true], $this->responseToArray($result)),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 400,
                'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, $exception->getMessage()),
            ];
        }
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function dispatchSearchImpersonableUsers(string $resolveKey, string $rawBody, TenantImpersonationAdapterInterface $adapter): array
    {
        $payload = $this->decodeJson($rawBody);
        if ($payload === null) {
            return [
                'status' => 400,
                'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, 'Invalid JSON body.'),
            ];
        }

        try {
            $dto = ImpersonableUserSearchRequestDTO::fromArray($payload);
            $result = $adapter->searchImpersonableUsers($resolveKey, $dto);

            return [
                'status' => 200,
                'body' => array_merge(['ok' => true], $result->toArray()),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 400,
                'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, $exception->getMessage()),
            ];
        }
    }

    /**
     * @return array{status:int, body:array<string,mixed>}
     */
    private function dispatchImpersonateUser(string $resolveKey, string $rawBody, TenantImpersonationAdapterInterface $adapter): array
    {
        $payload = $this->decodeJson($rawBody);
        if ($payload === null) {
            return [
                'status' => 400,
                'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, 'Invalid JSON body.'),
            ];
        }

        try {
            $dto = ImpersonationRequestDTO::fromArray($payload);
            $result = $adapter->impersonateUser($resolveKey, $dto);

            return [
                'status' => 200,
                'body' => array_merge(['ok' => true], $result->toArray()),
            ];
        } catch (\Throwable $exception) {
            return [
                'status' => 400,
                'body' => $this->errorBody(ErrorCode::VALIDATION_ERROR, $exception->getMessage()),
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

    /**
     * @return array<string, mixed>
     */
    private function errorBody(ErrorCode $code, string $message): array
    {
        return [
            'ok' => false,
            'code' => $code->value,
            'message' => $message,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseToArray(TenantLifecycleResponseDTO $response): array
    {
        return $response->toArray();
    }
}
