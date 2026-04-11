# puyu-pe/sipro-internal-api-ci3

Bridge liviano para integrar `puyu-pe/sipro-internal-api-core` en aplicaciones CodeIgniter 3.

## Integración rápida (CI3)

1. Activar Composer autoload en CI3:
   - `application/config/config.php`
   - `$config['composer_autoload'] = TRUE;` o ruta a `vendor/autoload.php`.
2. Copiar los stubs desde `stubs/ci3/` hacia tu app (`application/controllers` y `application/config`).
3. Configurar `application/config/internal_api.php` con tus `keys` (ideal: cargar secretos desde ENV/archivo fuera del repo).
4. Implementar adapters SaaS:
   - `ApplicationTenantAdapter` que cumpla `PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantProvisioningAdapterInterface`.
   - `ApplicationTenantLifecycleAdapter` que cumpla `PuyuPe\SiproInternalApiCore\Contracts\Adapter\TenantLifecycleAdapterInterface`.

   El lifecycle adapter debe implementar los 5 métodos:
   - `warnTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO`
   - `suspendTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO`
   - `activateTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO`
   - `closeTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO`
   - `reopenTenant(string $appKey, TenantLifecycleRequestDTO $dto): TenantLifecycleResponseDTO`

5. Probar con requests firmados desde SIPRO.

## Rutas disponibles

- `POST /internal/v1/tenants` — provisioning
- `POST /internal/v1/tenants/{appKey}:warn`
- `POST /internal/v1/tenants/{appKey}:suspend`
- `POST /internal/v1/tenants/{appKey}:activate`
- `POST /internal/v1/tenants/{appKey}:close`
- `POST /internal/v1/tenants/{appKey}:reopen`

## Notas de comportamiento

- El controller usa `$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)` para firmar/verificar **sin querystring**.
- Si `nonce_enabled=true`, se usa `DbNonceStore` y un nonce repetido debe fallar en verificación HMAC.

## Testing

- Unit tests del paquete (sin boot de CI3): `vendor/bin/phpunit`.
- Opcional: puedes usar `kenjis/ci-phpunit-test` para probar el controller stub dentro de una app CI3 real; no es requisito de este paquete.

## Desarrollo en el workspace

Este paquete forma parte del workspace `sipro-internal-workspace` junto a `core/`, `api-laravel/` y `clone-db/`. Cuando trabajás en el workspace con todos los submodulos clonados, `composer install --dev` resuelve `sipro-internal-api-core` via `path` repository (symlink a `../core`), sin necesidad de red. Fuera del workspace, se instala desde el registro de Composer normalmente.
