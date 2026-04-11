<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PuyuPe\SiproInternalApiCi3\Security\VerifyHmac;
use PuyuPe\SiproInternalApiCore\Security\Hmac\HmacSigner;
use PuyuPe\SiproInternalApiCore\Security\Hmac\NonceStoreInterface;

final class VerifyHmacTest extends TestCase
{
    private const KEY_ID = 'sipro-2026-01';
    private const SECRET = 'super-shared-secret';

    private HmacSigner $signer;
    private VerifyHmac $verify;

    protected function setUp(): void
    {
        $this->signer = new HmacSigner();
        $this->verify = new VerifyHmac();
    }

    /** @return callable(string): ?string */
    private function resolver(): callable
    {
        return static fn(string $id): ?string => $id === self::KEY_ID ? self::SECRET : null;
    }

    /**
     * @return array<string, string>
     */
    private function validHeaders(string $method, string $path, string $rawBody, string $timestamp, string $nonce): array
    {
        return $this->signer->buildSignedHeaders($method, $path, $rawBody, self::KEY_ID, self::SECRET, $timestamp, $nonce);
    }

    public function testMissingHeadersReturnsValidationError400(): void
    {
        $result = $this->verify->verifyOrError('POST', '/internal/v1/tenants', '{}', [], $this->resolver());

        self::assertNotNull($result);
        self::assertSame(400, $result['status']);
        self::assertSame('VALIDATION_ERROR', $result['body']['code']);
    }

    public function testBadSignatureReturns401InvalidSignature(): void
    {
        $headers = $this->validHeaders('POST', '/internal/v1/tenants', '{}', (string) time(), 'nonce-bad-sig');
        $headers['X-Internal-Signature'] = 'bad-signature';

        $result = $this->verify->verifyOrError('POST', '/internal/v1/tenants', '{}', $headers, $this->resolver());

        self::assertNotNull($result);
        self::assertSame(401, $result['status']);
        self::assertSame('INVALID_SIGNATURE', $result['body']['code']);
    }

    public function testExpiredTimestampReturns401RequestExpired(): void
    {
        $expiredTimestamp = (string) (time() - 301);
        $headers = $this->validHeaders('POST', '/internal/v1/tenants', '{}', $expiredTimestamp, 'nonce-expired');

        $result = $this->verify->verifyOrError('POST', '/internal/v1/tenants', '{}', $headers, $this->resolver());

        self::assertNotNull($result);
        self::assertSame(401, $result['status']);
        self::assertSame('REQUEST_EXPIRED', $result['body']['code']);
    }

    public function testNonceReplayReturns401NonceReplay(): void
    {
        $store = new class() implements NonceStoreInterface {
            /** @var array<string, int> */
            private array $store = [];

            public function has(string $nonce): bool
            {
                return isset($this->store[$nonce]);
            }

            public function put(string $nonce, int $ttlSeconds): void
            {
                $this->store[$nonce] = $ttlSeconds;
            }
        };

        $timestamp = (string) time();
        $nonce = 'nonce-replay';
        $headers = $this->validHeaders('POST', '/internal/v1/tenants', '{}', $timestamp, $nonce);

        $first = $this->verify->verifyOrError('POST', '/internal/v1/tenants', '{}', $headers, $this->resolver(), $store);
        $second = $this->verify->verifyOrError('POST', '/internal/v1/tenants', '{}', $headers, $this->resolver(), $store);

        self::assertNull($first);
        self::assertNotNull($second);
        self::assertSame(401, $second['status']);
        self::assertSame('NONCE_REPLAY', $second['body']['code']);
    }

    public function testValidRequestReturnsNull(): void
    {
        $timestamp = (string) time();
        $nonce = 'nonce-valid';
        $headers = $this->validHeaders('POST', '/internal/v1/tenants', '{}', $timestamp, $nonce);

        $result = $this->verify->verifyOrError('POST', '/internal/v1/tenants', '{}', $headers, $this->resolver());

        self::assertNull($result);
    }
}
