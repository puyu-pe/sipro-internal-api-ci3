<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PuyuPe\SiproInternalApiCi3\Support\Headers;

final class HeadersTest extends TestCase
{
    public function testFromGlobalsNormalizesServerHeadersToLowerCase(): void
    {
        $result = Headers::fromGlobals([
            'HTTP_X_API_KEY' => 'abc',
            'CONTENT_TYPE' => 'application/json',
        ]);

        self::assertSame('abc', $result['x-api-key']);
        self::assertSame('application/json', $result['content-type']);
    }

    public function testNormalizePreservesCanonicalHeaderValues(): void
    {
        $result = Headers::normalize([
            'X-Internal-Signature' => 'sig',
        ]);

        self::assertSame('sig', $result['x-internal-signature']);
    }
}
