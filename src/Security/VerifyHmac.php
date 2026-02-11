<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Security;

use PuyuPe\SiproInternalApiCore\Contracts\NonceStoreInterface;
use PuyuPe\SiproInternalApiCore\Errors\ErrorFactory;
use PuyuPe\SiproInternalApiCore\Errors\ErrorResponse;
use PuyuPe\SiproInternalApiCore\Security\HmacVerifier;

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
        $verifier = new HmacVerifier($resolveSecretByKeyId, $nonceStore);

        try {
            $result = $verifier->verify($method, $path, $rawBody, $headers);

            if ($result === true || $result === null) {
                return null;
            }

            if ($result instanceof ErrorResponse) {
                $body = $result->toArray();
                return [
                    'status' => $this->statusFromCode((string)($body['code'] ?? 'VALIDATION_ERROR')),
                    'body' => $body,
                ];
            }

            if (is_array($result)) {
                $code = (string)($result['code'] ?? 'VALIDATION_ERROR');
                return [
                    'status' => $this->statusFromCode($code),
                    'body' => $result,
                ];
            }

            return [
                'status' => 400,
                'body' => ErrorFactory::validationError('Unexpected verifier response.')->toArray(),
            ];
        } catch (\Throwable $exception) {
            $code = $this->extractErrorCode($exception);
            $status = $this->statusFromCode($code);

            if ($code === 'VALIDATION_ERROR') {
                $error = ErrorFactory::validationError($exception->getMessage());
            } elseif (method_exists(ErrorFactory::class, 'authError')) {
                $error = ErrorFactory::authError($code, $exception->getMessage());
            } else {
                $error = ErrorFactory::validationError($exception->getMessage());
            }

            return [
                'status' => $status,
                'body' => $error->toArray(),
            ];
        }
    }

    private function statusFromCode(string $code): int
    {
        return match ($code) {
            'REQUEST_EXPIRED', 'NONCE_REPLAY', 'INVALID_SIGNATURE' => 401,
            default => 400,
        };
    }

    private function extractErrorCode(\Throwable $exception): string
    {
        if (method_exists($exception, 'getErrorCode')) {
            $value = $exception->getErrorCode();
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $message = strtoupper($exception->getMessage());
        foreach (['REQUEST_EXPIRED', 'NONCE_REPLAY', 'INVALID_SIGNATURE', 'VALIDATION_ERROR'] as $code) {
            if (str_contains($message, $code)) {
                return $code;
            }
        }

        return 'VALIDATION_ERROR';
    }
}
