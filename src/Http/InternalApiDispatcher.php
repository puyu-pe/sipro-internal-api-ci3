<?php

declare(strict_types=1);

namespace PuyuPe\SiproInternalApiCi3\Http;

use Closure;
use PuyuPe\SiproInternalApiCore\Http\InternalApi;
use PuyuPe\SiproInternalApiCore\Http\Response\ApiResponse;
use PuyuPe\SiproInternalApiCore\Errors\{ErrorCode, InternalApiError};
use PuyuPe\SiproInternalApiCore\Security\Hmac\HmacVerifier;
use Throwable;

final class InternalApiDispatcher
{
    private readonly Closure $resolveAdapter;
    private readonly Closure $nonceStore;
    private readonly Closure $report;

    public function __construct(private readonly array $config, callable $resolveAdapter, callable $nonceStore, ?callable $report = null)
    {
        $this->resolveAdapter = Closure::fromCallable($resolveAdapter);
        $this->nonceStore = Closure::fromCallable($nonceStore);
        $this->report = Closure::fromCallable($report ?? static function (Throwable $error): void { error_log($error::class . ': ' . $error->getMessage()); });
    }

    public function dispatch(string $method, string $path, string $rawBody, array $headers): ApiResponse
    {
        try {
            $hmac = $this->config['hmac'];
            $duration = $this->config['impersonation']['duration'];
            $enabled = $this->config['enabled'];
            $api = new InternalApi(
                resolveAdapter: $this->resolveAdapter,
                resolveSecret: static fn (string $keyId): ?string => $hmac['keys'][$keyId] ?? null,
                verifier: new HmacVerifier(allowedClockSkewSeconds: $hmac['allowed_clock_skew_seconds']),
                nonceStore: $enabled && $hmac['nonce']['enabled'] ? ($this->nonceStore)() : null,
                defaultDurationMinutes: $duration['default_minutes'],
                minDurationMinutes: $duration['min_minutes'],
                maxDurationMinutes: $duration['max_minutes'],
                enabled: $enabled,
                report: $this->report
            );
            return $api->handle($method, $path, $rawBody, $headers);
        } catch (Throwable $error) {
            ($this->report)($error);
            return ApiResponse::error(new InternalApiError(ErrorCode::INTERNAL_ERROR, 'Internal API configuration failed.'));
        }
    }
}
