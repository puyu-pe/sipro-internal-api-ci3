# Sipro Internal API CI3 2.0

Bridge CodeIgniter 3 / PHP >=8.1 sobre core `^2.0.0`. Comparte con Laravel el
despacho de las diez operaciones, validación, errores, HMAC y políticas de
impersonación. El adaptador opcional clone-db también admite PHP >=8.1.

El bridge se prueba con CI3 oficial **3.1.13** y **Pocketarc 3.3.0**, utilizado por
Yubiz. Ambos frameworks figuran únicamente en `require-dev` para ejecutar la matriz:
instalar el bridge no instala ni reemplaza el framework del consumidor.

## Integración

1. Instalar `puyu-pe/sipro-internal-api-ci3:^2.0` y activar Composer autoload en CI3.
2. Copiar `stubs/ci3/application/controllers/InternalApi.php` a la aplicación.
3. Incorporar `config/internal_api.php` y fusionar las rutas de `config/routes.php`
   antes de rutas genéricas existentes. La ruta debe aceptar también el segmento
   adicional de `:impersonation-users/search`.
4. Configurar secretos en `hmac.keys` y factories en `adapters.provisioning`,
   `lifecycle`, `clone` e `impersonation`. Cada factory recibe el controlador CI3:

```php
'adapters' => [
    'provisioning' => static fn ($ci) => new ApplicationProvisioningAdapter($ci),
    'lifecycle' => static fn ($ci) => new ApplicationLifecycleAdapter($ci),
    'clone' => static fn ($ci) => new ApplicationCloneAdapter($ci),
    'impersonation' => static fn ($ci) => new ApplicationImpersonationAdapter($ci),
],
```

Cada resultado debe implementar la interfaz correspondiente en
`PuyuPe\SiproInternalApiCore\Contracts\Adapter`. La resolución inválida devuelve
`500 ADAPTER_NOT_CONFIGURED`. `enabled=false` devuelve 404.

Si la aplicación utiliza CSRF, agregue el prefijo interno a `csrf_exclude_uris`
(por ejemplo, `internal/v1(?:/.*)?`); estas solicitudes se autentican mediante HMAC.
Mantenga las protecciones de las demás rutas de la aplicación.

## Operaciones

Todas son POST bajo `/internal/v1`:

- `/tenants`: provisioning.
- `/tenants/{resolveKey}:warn`, `:suspend`, `:activate`, `:close`, `:reopen`.
- `/tenants/{resolveKey}:export`, `:import`.
- `/tenants/{resolveKey}:impersonation-users/search` y `:impersonate`.

La identidad del payload es `resolveKey` (`project.resolveKey` en provisioning)
y coincide con la ruta. El éxito devuelve `{ok:true,...camposDTO}`. Los errores
usan `{ok:false,error:{code,message,details?}}`, igual que Laravel. Provisioning
conserva su respuesta canónica snake_case con `resolve_key`.

## Nonce y conexión master

Instale `stubs/ci3/internal_api_nonces.sql` en una DB master dedicada. Configure
`hmac.nonce.connection` con el nombre de la conexión CI3 y `db_debug=false` en
esa conexión, de modo que los errores puedan convertirse en respuestas JSON.
La tabla usa clave única y expiración en epoch seconds; no reutilice un esquema
antiguo con columnas o tipos diferentes.

La reserva es atómica y ocurre después de verificar la firma. La conexión se
obtiene como instancia independiente para no seguir cambios de DB del tenant.
Ejecute periódicamente el DELETE de expirados documentado en el SQL.
`hmac.allowed_clock_skew_seconds` vale 300 por defecto. El nonce es obligatorio
en la firma incluso si `hmac.nonce.enabled=false`.

## Helpers y migración

Los helpers de provisioning equivalentes a Laravel están en
`Adapters\AbstractTenantProvisioningAdapter`: reciben una conexión CI3 y permiten
`usingDatabase()` con restauración en `finally`. Defina `databasePrefix()` solo si
usa el generador predeterminado de nombres; puede sustituir `buildDatabaseName()`.
El upsert de parámetros no duplica registros cuando el valor ya es el mismo.

La excepción de negocio compartida es `Core\Errors\InternalApiException`, que
envuelve `InternalApiError`. Se eliminan el wrapper CI3 `VerifyHmac`, la firma
anterior del dispatcher y `Headers::fromEnvironment`. Utilice el controlador y
configuración 2.0 completos; no basta con actualizar el paquete dejando stubs 1.x.
No se aceptan aliases de identidad ni firmas base64.

## Pruebas

`composer test` arranca CI3 3.1.13 real en un servidor PHP local y utiliza el
controlador/config/rutas distribuidos. Las pruebas con `SIPRO_TEST_DB_HOST`
comprueban nonces concurrentes, expiración y helpers sobre MariaDB desechable.
`SIPRO_CLONE_DB_ROOT` habilita el round trip HTTP de clone-db en PHP >=8.1.
Use `SIPRO_CI3_FRAMEWORK=pocketarc/codeigniter composer test` para ejecutar la misma
suite con Pocketarc. El runtime del bridge se verifica en PHP 8.1–8.4.
