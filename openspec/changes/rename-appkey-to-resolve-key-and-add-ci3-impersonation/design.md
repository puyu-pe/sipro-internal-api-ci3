# Design: Rename appKey to resolveKey and Add CI3 Impersonation

## Executive Summary

This change renames `appKey` to `resolveKey` across all core DTOs and adapter interfaces — a breaking change that aligns the API contract with the actual domain terminology (`resolve_key` in the DB). It also ports the full impersonation capability (search + impersonate + duration) from api-laravel to api-ci3, giving CI3 the same feature set.

## Goals

- Single consistent tenant identifier name across all packages: `resolveKey`
- CI3 has feature parity with Laravel for impersonation
- All existing routes work with the renamed field
- Tests validate the new naming and new CI3 impersonation routes

## Non-Goals

- Backward-compatible dual-read of both `appKey` and `resolveKey`
- Renaming the HTTP URL path segments (paths keep `{appKey}` or `{resolveKey}` as route param)
- Modifying yubus database schema or business logic

## Rename Details

### Core DTOs — property and JSON key rename

Every DTO that currently has `public readonly string $appKey` becomes `public readonly string $resolveKey`.

The `fromArray` method reads `$payload['resolveKey']` instead of `$payload['appKey']`.
The `toArray` method returns `'resolveKey' => $this->resolveKey`.

Affected DTOs:
- `TenantLifecycleRequestDTO`
- `TenantLifecycleResponseDTO`
- `TenantExportRequestDTO`
- `TenantExportResponseDTO`
- `TenantImportRequestDTO`
- `TenantImportResponseDTO`
- `ImpersonationRequestDTO`
- `ImpersonationResponseDTO`
- `ImpersonableUserSearchRequestDTO`
- `ImpersonableUserSearchResponseDTO`
- `ProvisionProjectDTO` (nested `appKey` → `resolveKey`)
- `ProvisionResponseDTO` (nested `appKey` → `resolveKey`)

### Adapter interfaces — parameter rename

All interface methods that take `string $appKey` become `string $resolveKey`:
- `TenantLifecycleAdapterInterface` (warnTenant, suspendTenant, activateTenant, closeTenant, reopenTenant)
- `TenantProvisioningAdapterInterface` (createTenant)
- `TenantCloneAdapterInterface` (exportTenant, importTenant)
- `TenantImpersonationAdapterInterface` (searchImpersonableUsers, impersonateUser)

### Error messages and validation

Validation error field names change from `'appKey'` to `'resolveKey'`.

## CI3 Dispatcher Changes

### Constructor

Add `TenantImpersonationAdapterInterface` as an optional dependency to avoid breaking existing callers that don't use impersonation:

```php
public function dispatch(
    string $method,
    string $path,
    string $rawBody,
    array $headers,
    TenantProvisioningAdapterInterface $provisioningAdapter,
    TenantLifecycleAdapterInterface $lifecycleAdapter,
    ?TenantImpersonationAdapterInterface $impersonationAdapter = null
): array
```

### New routes

Add routing for:
- `POST /internal/v1/tenants/{resolveKey}:impersonation-users/search`
- `POST /internal/v1/tenants/{resolveKey}:impersonate`

### Dispatch methods

Add:
- `dispatchSearchImpersonableUsers(string $resolveKey, string $rawBody, TenantImpersonationAdapterInterface $adapter)`
- `dispatchImpersonateUser(string $resolveKey, string $rawBody, TenantImpersonationAdapterInterface $adapter)`

### Error handling

If impersonationAdapter is null when a route matches, return 501 Not Implemented.

## API Laravel Updates

- Controller method params: `$appKey` → `$resolveKey`
- Config key if it references `appKey` in impersonation context → `resolveKey`
- Test fixtures (FakeTenantAdapter) param names
- No route path changes needed; route param name is internal to Laravel

## Yubus Updates

- Adapter method params: `$appKey` → `$resolveKey`
- Config key if it references `appKey` → `resolveKey`
- No business logic changes needed

## Version Strategy

- core: `1.4.0` (breaking rename within 1.x; or `2.0.0` if strict semver is preferred)
- api-ci3: `1.2.0` (new impersonation routes + core ^1.4.0 dependency)
- api-laravel: `1.4.0` (param rename + core ^1.4.0 dependency)

## Migration Guide for Consumers

Before:
```json
{"appKey": "tenant-app", "projectCode": "PROJ"}
```

After:
```json
{"resolveKey": "tenant-app", "projectCode": "PROJ"}
```

Same applies to all lifecycle, clone, and impersonation request payloads.
