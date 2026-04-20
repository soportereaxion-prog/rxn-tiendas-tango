# Release 1.16.5 — DevDbSwitcher (dropdown admin para alternar DBs en dev)

**Fecha**: 2026-04-20
**Build**: 20260420.4
**Scope**: dev tooling (admin topbar)
**Tipo**: feature chiquita, dev only

---

## Contexto

Charly va a desarrollar un módulo de re-envío masivo de PDS a Tango (UM + equivalencia) y necesita trabajar contra un snapshot de la DB de prod levantado localmente. La fricción de editar el `.env` + reiniciar Apache cada vez que cambia de DB es alta. Pidió una "ventanita" dentro de la app para alternar bases, con la restricción dura de que no suba al reino.

## Qué se hizo

Dropdown en el topbar admin (al lado del 🌙 del tema) que permite a rxn_admin alternar entre las DBs declaradas en `config/dev_databases.local.php`. Cambio por sesión (`$_SESSION['dev_db_override']`). Badge visual en rojo cuando la DB activa no es la primera del config (indicador visual de "estoy trabajando sobre un snapshot").

## Arquitectura

### Archivos nuevos

- **`app/shared/Services/DevDbSwitcher.php`** — helper estático. Única gate del feature es `isEnabled()` que chequea la existencia del archivo config. `getAvailable()` / `getActiveOverride()` / `setActive()` completan la API. El `setActive` es whitelist-based: solo acepta nombres declarados en el config (previene manipulación por user).
- **`app/modules/Admin/Controllers/DevDbSwitchController.php`** — un solo endpoint `switch()`. Guard `requireRxnAdmin` + doble check de `DevDbSwitcher::isEnabled()` (responde 404 si no hay config).
- **`config/dev_databases.local.php.example`** — plantilla con docs inline, commiteable.
- **`config/dev_databases.local.php`** — archivo real de Charly, **gitignored**.

### Archivos modificados

- **`app/config/database.php`** — antes de armar el array de config, chequea `DevDbSwitcher::getActiveOverride()`. Si hay override, reemplaza el `dbname` del .env. Las credenciales (host/user/pass) siguen saliendo del .env — solo cambia el nombre de la DB.
- **`app/config/routes.php`** — ruta POST `/admin/dev-db-switch` registrada.
- **`app/shared/views/components/backoffice_user_banner.php`** — dropdown condicional (se renderiza solo si `isEnabled() && isRxnAdmin`). Select con `onchange="this.form.submit()"`. Texto en rojo cuando la DB activa no es la primera del config.
- **`.gitignore`** — `/config/dev_databases.local.php` agregado (el `.example` sigue trackeado como plantilla).

## Garantías de que NO sube al OTA

El `ReleaseBuilder` (ver `app/core/ReleaseBuilder.php`) es whitelist-based:

```php
$whitelistRegex = '/^(app|public|vendor|database|deploy_db|composer\.json|composer\.lock)/i';
```

`config/` no matchea → **los archivos de configuración local nunca se incluyen en el OTA**.

Dry-run verificado:

```
✗ NO SUBE — config/dev_databases.local.php
✗ NO SUBE — config/dev_databases.local.php.example
✓ SUBE     — app/shared/Services/DevDbSwitcher.php
✓ SUBE     — app/modules/Admin/Controllers/DevDbSwitchController.php
✓ SUBE     — app/config/database.php
```

El código PHP sí sube (forma parte del core de la app), pero queda **inerte en prod**:

- `DevDbSwitcher::isEnabled()` devuelve `false` → el dropdown no se renderiza.
- El endpoint `/admin/dev-db-switch` responde 404 con `http_response_code(404)` en el mismo `switch()`.

## Smoke test

```php
isEnabled: true
getAvailable:
Array ( [rxn_suite] => 🟢 Dev local [rxn_suite_prod] => ⚠ Snapshot prod )
getActiveOverride (sin sesión): NULL
setActive(rxn_suite_prod): true
getActiveOverride (post set): 'rxn_suite_prod'
setActive(rxn_hacker_db): false       ← whitelist rechaza nombres no declarados
post reset (setActive('')): NULL
```

Todos los linters PHP (`-l`) pasan sin errores de sintaxis.

## Decisiones tomadas

- **Por qué NO un módulo completo con UI rica** (borrador original): Charly prefirió la versión mínima. La UX "dropdown en topbar" es suficiente y no justifica un módulo separado.
- **Por qué whitelist-based en `setActive`**: evita que alguien con acceso a la sesión PHP inyecte nombres arbitrarios (SQL injection vía dbname).
- **Por qué override por sesión y no global**: cada dev puede trabajar con su propia DB sin afectar a otros que usen la misma instancia local.
- **Por qué credenciales del .env**: el caso de uso es "misma instancia MySQL, distintas DBs". Si alguien necesita otro host/user/pass, que lo ponga en otro .env y no use este switcher.
- **Por qué badge rojo condicional**: trabajar sobre un snapshot de prod sin saberlo es el riesgo principal. El badge rojo permanente arriba del topbar es el guard visual más barato y efectivo.

## Uso

1. Copiar `config/dev_databases.local.php.example` como `config/dev_databases.local.php`.
2. Editar con las DBs que tengas levantadas localmente. **La primera del array debería matchear el `DB_NAME` del .env** para que el badge refleje correctamente "DB default".
3. Reload. En el topbar admin aparece el dropdown "DB".
4. Cambiar → el select se pone rojo si no es la default → hacer reload de la página para que el próximo request use la DB nueva.

## Regla actualizada en CLAUDE.md

Al cerrar esta sesión Charly me pidió que anote: **cerrar sesión SIEMPRE significa OTA** (convención reinvertida respecto del 18-04-2026). Actualicé `CLAUDE.md` del proyecto con el historial completo de la regla y la excepción inversa (Charly puede pedir OTA sin cierre).

## Pendiente

- Charly va a probar el switcher. Si hay ajustes de UX, iteramos.
- El módulo de re-envío masivo de PDS a Tango arranca en la próxima sesión — contexto ya guardado en Engram (`pds/reenvio-masivo-tango-um-equivalencia`).
