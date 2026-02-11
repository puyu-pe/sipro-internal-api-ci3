<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PuyuPe\SiproInternalApiCi3\Security\VerifyHmac;
use PuyuPe\SiproInternalApiCore\Contracts\NonceStoreInterface;

final class VerifyHmacTest extends TestCase
{
    public function testMissingHeadersReturnsValidationError400(): void
    {
        $verify = new VerifyHmac();

        $result = $verify->verifyOrError('POST', '/internal/v1/tenants', '{}', [], static fn(string $id): ?string => 'secret');

        self::assertNotNull($result);
        self::assertSame(400, $result['status']);
        self::assertSame('VALIDATION_ERROR', $result['body']['code']);
    }

    public function testBadSignatureReturns401InvalidSignature(): void
    {
        $verify = new VerifyHmac();

        $result = $verify->verifyOrError('POST', '/internal/v1/tenants', '{}', [
            'x-key-id' => 'sipro-2026-01',
            'x-signature' => 'bad-signature',
            'x-timestamp' => (string) time(),
            'x-nonce' => 'nonce-1',
        ], static fn(string $id): ?string => 'secret');

        self::assertNotNull($result);
        self::assertSame(401, $result['status']);
        self::assertSame('INVALID_SIGNATURE', $result['body']['code']);
    }

    public function testExpiredTimestampReturns401RequestExpired(): void
    {
        $verify = new VerifyHmac();

        $result = $verify->verifyOrError('POST', '/internal/v1/tenants', '{}', [
            'x-key-id' => 'sipro-2026-01',
            'x-signature' => 'good-signature',
            'x-timestamp' => (string) (time() - 301),
            'x-nonce' => 'nonce-2',
        ], static fn(string $id): ?string => 'secret');

        self::assertNotNull($result);
        self::assertSame(401, $result['status']);
        self::assertSame('REQUEST_EXPIRED', $result['body']['code']);
    }

    public function testNonceReplayReturns401NonceReplay(): void
    {
        $verify = new VerifyHmac();
        $store = new class() implements NonceStoreInterface {
            /** @var array<string,int> */
            private array $store = [];
            public function has(string $nonceKey): bool
            {
                return isset($this->store[$nonceKey]);
            }
            public function put(string $nonceKey, int $expiresAt): void
            {
                $this->store[$nonceKey] = $expiresAt;
            }
        };

        $headers = [
            'x-key-id' => 'sipro-2026-01',
            'x-signature' => 'good-signature',
            'x-timestamp' => (string) time(),
            'x-nonce' => 'nonce-replay',
        ];

        $first = $verify->verifyOrError('POST', '/internal/v1/tenants', '{}', $headers, static fn(string $id): ?string => 'secret', $store);
        $second = $verify->verifyOrError('POST', '/internal/v1/tenants', '{}', $headers, static fn(string $id): ?string => 'secret', $store);

        self::assertNull($first);
        self::assertNotNull($second);
        self::assertSame(401, $second['status']);
        self::assertSame('NONCE_REPLAY', $second['body']['code']);
    }
}
