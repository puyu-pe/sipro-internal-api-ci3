<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Security;

use PuyuPe\SiproInternalApiCore\Errors\ErrorFactory;
use PuyuPe\SiproInternalApiCore\Security\Hmac\HmacVerifier;
use PuyuPe\SiproInternalApiCore\Security\Hmac\NonceStoreInterface;

final class VerifyHmac
{
    /**
     * @param callable(string):?string $resolveSecretByKeyId
     * @return array{status:int, body:array<string,mixed>}|null
     */
    public function verifyOrError(
        string $method,
        string $path,
        string $rawBody,
        array $headers,
        callable $resolveSecretByKeyId,
        ?NonceStoreInterface $nonceStore = null
    ): ?array {
        $verifier = new HmacVerifier();

        $result = $verifier->verify($method, $path, $rawBody, $headers, $resolveSecretByKeyId, $nonceStore);

        if ($result->ok) {
            return null;
        }

        $code = $result->errorCode?->value ?? 'VALIDATION_ERROR';

        return [
            'status' => $this->statusFromCode($code),
            'body' => [
                'ok' => false,
                'code' => $code,
                'message' => $result->errorMessage ?? 'Verification failed.',
            ],
        ];
    }

    private function statusFromCode(string $code): int
    {
        return match ($code) {
            'REQUEST_EXPIRED', 'NONCE_REPLAY', 'INVALID_SIGNATURE' => 401,
            default => 400,
        };
    }
}
