# Tasks: Rename appKey to resolveKey and Add CI3 Impersonation

## Phase 1: Core Rename

- [x] 1.1 Rename `appKey` → `resolveKey` in all core DTOs: `TenantLifecycleRequestDTO`, `TenantLifecycleResponseDTO`, `TenantExportRequestDTO`, `TenantExportResponseDTO`, `TenantImportRequestDTO`, `TenantImportResponseDTO`, `ImpersonationRequestDTO`, `ImpersonationResponseDTO`, `ImpersonableUserSearchRequestDTO`, `ImpersonableUserSearchResponseDTO`, `ProvisionProjectDTO`, `ProvisionResponseDTO`. Update constructor param, `fromArray` payload key, `toArray` output key, and validation error field names.
- [x] 1.2 Rename `$appKey` → `$resolveKey` parameter in all core adapter interfaces: `TenantLifecycleAdapterInterface`, `TenantProvisioningAdapterInterface`, `TenantCloneAdapterInterface`, `TenantImpersonationAdapterInterface`.
- [x] 1.3 Rename `$appKey` → `$resolveKey` in `ErrorFactory` methods that reference it (e.g., `tenantNotFound`, `userNotFound`, `impersonationFailed`).

## Phase 2: Core Tests

- [x] 2.1 Update all core test files to use `resolveKey` instead of `appKey` in DTO construction and assertions: `TenantLifecycleDTOTest`, `TenantCloneDTOTest`, `ProvisionPayloadDTOTest`, `ProvisionResponseDTOTest`, `ImpersonationDTOTest`.
- [x] 2.2 Update `ErrorFactoryTest` assertions if error detail keys changed.

## Phase 3: CI3 Dispatcher — Impersonation Routes

- [x] 3.1 Add `TenantImpersonationAdapterInterface` as an optional dependency to `InternalApiDispatcher::dispatch()`.
- [x] 3.2 Add routing for `:impersonation-users/search` and `:impersonate` paths in the dispatcher.
- [x] 3.3 Implement `dispatchSearchImpersonableUsers()` method: decode JSON, build `ImpersonableUserSearchRequestDTO`, call adapter, return response.
- [x] 3.4 Implement `dispatchImpersonateUser()` method: decode JSON, build `ImpersonationRequestDTO`, call adapter, return response.
- [x] 3.5 Return 501 Not Implemented if impersonation adapter is null when routes match.

## Phase 4: CI3 Tests

- [x] 4.1 Update existing `InternalApiDispatcherTest` to use `resolveKey` instead of `appKey` in all test payloads.
- [x] 4.2 Add tests for impersonation search route dispatch.
- [x] 4.3 Add tests for impersonation route dispatch.
- [x] 4.4 Add test for 501 when impersonation adapter is null.
- [x] 4.5 Update `VerifyHmacTest` and other tests if they reference `appKey`.

## Phase 5: API Laravel — Param Rename

- [x] 5.1 Rename `$appKey` → `$resolveKey` in `InternalV1Controller` method parameters and local variables.
- [x] 5.2 Update config key references from `appKey` to `resolveKey` if applicable.
- [x] 5.3 Update `FakeTenantAdapter` test fixture param names.
- [x] 5.4 Update `InternalV1EndpointsTest` payloads to use `resolveKey`.
- [x] 5.5 Update `TestCase` fixture setup if it uses `appKey`.

## Phase 6: Yubus — Adapter Param Rename

- [ ] 6.1 Rename `$appKey` → `$resolveKey` in `YubusTenantImpersonationAdapter` method params.
- [ ] 6.2 Rename `$appKey` → `$resolveKey` in `YubusTenantAdapter` and `YubusTenantLifecycleAdapter` method params if they exist.
- [ ] 6.3 Update yubus config key references if applicable.
- [ ] 6.4 Update yubus tests (`YubusTenantImpersonationAdapterTest`, `SupportAccessServiceTest`) to use `resolveKey`.

## Phase 7: Version Bumps and Dependencies

- [x] 7.1 Bump core version to `1.4.0` in composer.json.
- [x] 7.2 Bump api-ci3 version to `1.2.0` in composer.json, update core dependency to `^1.4.0`.
- [x] 7.3 Bump api-laravel version to `1.4.0` in composer.json, update core dependency to `^1.4.0`.
- [x] 7.4 Run php -n -l syntax check on all changed PHP files.