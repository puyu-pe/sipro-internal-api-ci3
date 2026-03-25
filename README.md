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
5. Probar con requests firmados desde SIPRO.

## Notas de comportamiento

- El controller usa `$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)` para firmar/verificar **sin querystring**.
- Si `nonce_enabled=true`, se usa `DbNonceStore` y un nonce repetido debe fallar en verificación HMAC.


## Testing

- Unit tests del paquete (sin boot de CI3): `vendor/bin/phpunit`.
- Opcional: puedes usar `kenjis/ci-phpunit-test` para probar el controller stub dentro de una app CI3 real; no es requisito de este paquete.
