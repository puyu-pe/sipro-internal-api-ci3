# Proposal: Rename appKey to resolveKey and Add CI3 Impersonation

## Intent

Two coordinated changes:

1. **Breaking rename in core**: Replace `appKey` with `resolveKey` across all DTOs and adapter contracts. This aligns the API contract with the actual domain name used in yubus (`resolve_key`) and creates a consistent identifier across Laravel, CI3, and any future adapters.

2. **Add full impersonation support to CI3**: Port the impersonation discovery + duration implementation from api-laravel to api-ci3, giving CI3 the same capabilities: paginated search of eligible users and impersonation with configurable bounded duration.

## Scope

### In Scope
- Rename `appKey` → `resolveKey` in all core DTOs, adapter interfaces, and response types
- Update `fromArray` to read `resolveKey` (camelCase) from JSON payloads; breaking change — old `appKey` payloads will fail
- Add impersonation dispatch routes to `InternalApiDispatcher` in api-ci3:
  - `POST /internal/v1/tenants/{resolveKey}:impersonation-users/search`
  - `POST /internal/v1/tenants/{resolveKey}:impersonate`
- Add impersonation adapter interface dependency to `InternalApiDispatcher`
- Add tests for impersonation dispatch and rename across all repos
- Bump composer dependencies: api-ci3 and api-laravel require `^1.4.0` of core

### Out of Scope
- Changing the URL path structure of existing endpoints
- Modifying yubus business logic (already uses `resolve_key` in DB)
- Changing `/support/enter/{token}` browser flow

## Approach

### Rename strategy
Direct rename with no backward-compatibility shim. This is a breaking change:
- DTO constructor params: `appKey` → `resolveKey`
- `fromArray` JSON keys: `appKey` → `resolveKey`
- `toArray` output keys: `appKey` → `resolveKey`
- Adapter interface method signatures: `$appKey` → `$resolveKey`

Affected core files:
- `TenantLifecycleRequestDTO`, `TenantLifecycleResponseDTO`
- `TenantExportRequestDTO`, `TenantExportResponseDTO`
- `TenantImportRequestDTO`, `TenantImportResponseDTO`
- `ImpersonationRequestDTO`, `ImpersonationResponseDTO`
- `ImpersonableUserSearchRequestDTO`, `ImpersonableUserSearchResponseDTO`
- `ProvisionProjectDTO` (nested `appKey`)
- `ProvisionResponseDTO` (nested `appKey`)
- `TenantLifecycleAdapterInterface`
- `TenantProvisioningAdapterInterface`
- `TenantCloneAdapterInterface`
- `TenantImpersonationAdapterInterface`

### CI3 impersonation strategy
Mirror the api-laravel dispatcher pattern:
- Add `TenantImpersonationAdapterInterface` to dispatcher constructor
- Add routing for search and impersonate paths
- Delegate to adapter methods
- Return normalized JSON responses

## Affected Areas

| Area | Impact | Description |
|------|--------|-------------|
| `sipro-internal-api-core` | Breaking rename | All DTOs and adapter interfaces rename `appKey` → `resolveKey` |
| `sipro-internal-api-ci3` | Modified + new routes | Dispatcher gains impersonation routes; tests updated for rename |
| `sipro-internal-api-laravel` | Modified | Config key rename, controller param rename, test fixture updates |
| `yubus` | Modified | Adapter param rename, config key rename |

## Risks

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Breaking change breaks existing SIPRO callers | High | Coordinate with SIPRO team; publish migration guide; bump to v2.0.0 or v1.4.0 with clear changelog |
| Missed `appKey` references in some DTO or interface | Medium | Grep-based audit before commit; test all DTOs |
| CI3 impersonation path doesn't match Laravel | Low | Follow same endpoint naming convention as api-laravel |

## Rollback Plan

Revert the rename to `appKey` across all DTOs and remove impersonation routes from CI3 dispatcher.

## Dependencies

- Prior art: `extract-impersonation-to-package`, `improve-impersonation-discovery-duration`
- Core must be released first, then api-ci3 and api-laravel consume the new version

## Success Criteria

- [ ] No remaining `appKey` references in core DTOs or adapter interfaces (only internal variable names may vary)
- [ ] CI3 dispatcher routes impersonation search and impersonate correctly
- [ ] All existing lifecycle and provision routes continue working with `resolveKey`
- [ ] Tests pass for core, api-ci3, and api-laravel with renamed contracts
- [ ] API documentation updated to reflect `resolveKey` naming
