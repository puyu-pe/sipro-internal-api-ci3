<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCore\Errors;

final class ErrorResponse
{
    public function __construct(private readonly string $code, private readonly string $message)
    {
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['ok' => false, 'code' => $this->code, 'message' => $this->message];
    }
}

final class ErrorFactory
{
    public static function validationError(string $message): ErrorResponse
    {
        return new ErrorResponse('VALIDATION_ERROR', $message);
    }

    public static function routeNotFound(string $message): ErrorResponse
    {
        return new ErrorResponse('ROUTE_NOT_FOUND', $message);
    }

    public static function authError(string $code, string $message): ErrorResponse
    {
        return new ErrorResponse($code, $message);
    }
}

namespace PuyuPe\SiproInternalApiCore\Contracts;

interface NonceStoreInterface
{
    public function has(string $nonceKey): bool;

    public function put(string $nonceKey, int $expiresAt): void;
}

interface TenantAdapterInterface
{
    /** @return array<string,mixed> */
    public function createTenant(\PuyuPe\SiproInternalApiCore\Dto\CreateTenantRequest $dto): array;

    public function warnTenant(string $tenantUuid, \PuyuPe\SiproInternalApiCore\Dto\WarnTenantRequest $dto): void;

    public function suspendTenant(string $tenantUuid, \PuyuPe\SiproInternalApiCore\Dto\SuspendTenantRequest $dto): void;

    public function activateTenant(string $tenantUuid, \PuyuPe\SiproInternalApiCore\Dto\ActivateTenantRequest $dto): void;
}

namespace PuyuPe\SiproInternalApiCore\Dto;

abstract class BaseRequest
{
    /** @param array<string,mixed> $data */
    final public function __construct(public array $data)
    {
    }

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    public function validate(): void
    {
        if (($this->data['invalid'] ?? false) === true) {
            throw new \InvalidArgumentException('Invalid DTO');
        }
    }
}

final class CreateTenantRequest extends BaseRequest {}
final class WarnTenantRequest extends BaseRequest {}
final class SuspendTenantRequest extends BaseRequest {}
final class ActivateTenantRequest extends BaseRequest {}

namespace PuyuPe\SiproInternalApiCore\Security;

use PuyuPe\SiproInternalApiCore\Contracts\NonceStoreInterface;
use PuyuPe\SiproInternalApiCore\Errors\ErrorResponse;

final class HmacVerifier
{
    /** @param callable(string):?string $resolver */
    public function __construct(private $resolver, private ?NonceStoreInterface $nonceStore = null)
    {
    }

    public function verify(string $method, string $path, string $rawBody, array $headers): bool|ErrorResponse
    {
        $required = ['x-key-id', 'x-signature', 'x-timestamp', 'x-nonce'];
        foreach ($required as $headerName) {
            if (!isset($headers[$headerName]) || $headers[$headerName] === '') {
                return new ErrorResponse('VALIDATION_ERROR', 'Missing header: ' . $headerName);
            }
        }

        $keyId = (string) $headers['x-key-id'];
        $secret = ($this->resolver)($keyId);
        if (!is_string($secret) || $secret === '') {
            return new ErrorResponse('VALIDATION_ERROR', 'Unknown key id.');
        }

        $timestamp = (int) $headers['x-timestamp'];
        if ($timestamp < (time() - 300)) {
            return new ErrorResponse('REQUEST_EXPIRED', 'Request expired.');
        }

        $nonce = (string) $headers['x-nonce'];
        if ($this->nonceStore !== null) {
            $nonceKey = $keyId . ':' . $nonce;
            if ($this->nonceStore->has($nonceKey)) {
                return new ErrorResponse('NONCE_REPLAY', 'Nonce replay detected.');
            }
            $this->nonceStore->put($nonceKey, $timestamp + 300);
        }

        if ((string) $headers['x-signature'] !== 'good-signature') {
            return new ErrorResponse('INVALID_SIGNATURE', 'Invalid signature.');
        }

        return true;
    }
}
