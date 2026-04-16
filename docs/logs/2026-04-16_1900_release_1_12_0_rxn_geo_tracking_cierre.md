# Release 1.12.0 — RxnGeoTracking CIERRE (Sprints 3+4)

**Fecha:** 2026-04-16
**Build:** 20260416.3
**Iteración:** Sprints 3 y 4 del módulo RxnGeoTracking (fases 5 y 6 — **cierre del módulo**)

---

## Qué se hizo

Esta release cierra el módulo `RxnGeoTracking` según el plan de 6 fases original. Combina los sprints 3 (integración en controllers transaccionales) y 4 (job de purga periódica) en una sola release.

### Sprint 3 — Fase 5: Integración en transaccionales

Los 3 controllers que crean entidades comerciales ahora invocan el tracking:

- **`CrmPresupuestos\PresupuestoController::store()`** — `EVENT_PRESUPUESTO_CREATED` con `entidad_id=$presupuestoId`.
- **`CrmTratativas\TratativaController::store()`** — `EVENT_TRATATIVA_CREATED`.
- **`CrmPedidosServicio\PedidoServicioController::store()`** — `EVENT_PDS_CREATED`. Crucial: se invoca **antes** del branch de envío a Tango (porque el PDS ya existe en DB aunque Tango falle después).

Cada invocación es **fire-and-forget** con `try/catch Throwable`. El `evento_id` retornado queda en `$_SESSION['rxn_geo_pending_event_id']` para que el próximo render de `admin_layout` lo inyecte como `<meta name="rxn-pending-geo-event">` y el JS dispare `navigator.geolocation.getCurrentPosition()`.

### Sprint 4 — Fase 6: Job de purga

**Nuevo**: `app/modules/RxnGeoTracking/tools/purge_geo_events.php`

- Itera `SELECT DISTINCT empresa_id FROM rxn_geo_eventos` (solo empresas con eventos).
- Para cada empresa, lee `retention_days` de su config (default 90 si no tiene fila).
- Invoca `GeoEventRepository::purgeOlderThan($empresaId, $retention)`.
- Reporta resumen: empresas procesadas, empresas con purga, total eventos borrados.
- Flags: `--dry-run` (no borra, solo cuenta) y `--verbose` (muestra también empresas sin eventos viejos).

**Ubicación**: intencionalmente bajo `app/modules/RxnGeoTracking/tools/`, NO en `tools/` raíz. Razón: el `ReleaseBuilder` incluye `app/` en su whitelist pero **no** incluye `tools/`. Estando bajo `app/`, el script viaja al OTA automáticamente.

**Cron sugerido para prod** (3 AM diario, redirect de log):
```
0 3 * * * cd /var/www/rxn_suite && php app/modules/RxnGeoTracking/tools/purge_geo_events.php >> storage/logs/geo_purge.log 2>&1
```

---

## Cambios transversales

### Actualización de MODULE_CONTEXT

Se removieron las marcas **"(propuesto, no implementado al 2026-04-16)"** de:
- `app/modules/Auth/MODULE_CONTEXT.md`
- `app/modules/CrmPresupuestos/MODULE_CONTEXT.md`
- `app/modules/CrmPedidosServicio/MODULE_CONTEXT.md`
- `app/modules/CrmTratativas/MODULE_CONTEXT.md`

El MD del propio módulo `app/modules/RxnGeoTracking/MODULE_CONTEXT.md` pasó de header **"PROPUESTA / NO IMPLEMENTADO"** a **"IMPLEMENTADO (desde release 1.10.0 – 1.12.0)"**, con la sección de "Notas para implementación inicial" reemplazada por **"Historial de implementación"** con las 6 fases ✅.

### Config nueva

**`.env` local**: placeholder `GOOGLE_MAPS_API_KEY=` agregado con comentarios explicando cómo obtenerla:

```
# Google Maps — Dashboard RxnGeoTracking (sprint 2)
# Obtener en: https://console.cloud.google.com → APIs & Services → Credentials → Create Credentials → API Key
# Restringir por HTTP referrer al dominio del server (local + prod) para evitar robo de key.
# Si queda vacío, el dashboard se renderiza sin mapa (listado y export siguen funcionando).
GOOGLE_MAPS_API_KEY=
```

Charly todavía tiene que generar la key en Google Cloud y pegarla acá (local) + en el `.env` de prod.

### Modus operandi registrado en CLAUDE.md

Nueva sección en `CLAUDE.md` del proyecto: **"Modus operandi de cierre de sesión: Factory OTA automático"**. Establece que al terminar una sesión Lumi ejecuta bump + log + build OTA automáticamente sin esperar que Charly lo pida. Excepciones: sesión exploratoria, Charly pide dejarlo para otra sesión, o algo quedó roto sin validar.

---

## Por qué

Charly pidió explícitamente cerrar los sprints 3 y 4 en la misma sesión antes del OTA final. Al cerrar estas fases el módulo queda entregado completo según el plan original. También estableció el modus operandi de cierre con OTA automático como regla general del proyecto, no solo para este módulo.

---

## Impacto

- **Schema DB**: sin cambios (las 3 tablas del sprint 1 soportan todo).
- **Backend**: 3 controllers tocados con 10 líneas cada uno (patrón consistente).
- **Scripts**: 1 script CLI nuevo en `app/modules/RxnGeoTracking/tools/`.
- **Docs**: 5 MODULE_CONTEXT actualizados + 1 log nuevo + regla en CLAUDE.md.
- **Riesgo operativo**: **bajo**. El tracking sigue siendo fire-and-forget; los transaccionales no se rompen si el tracking falla. El script de purga tiene dry-run.

---

## Decisiones tomadas

1. **PDS: tracking antes del envío a Tango**, no después. Razón: el PDS ya existe en DB después del `create()` y el `syncAdjuntos`. Si Tango falla, el PDS sigue existiendo y debe haber sido trackeado. El evento captura la **creación**, no el envío.
2. **Script de purga dentro de `app/modules/RxnGeoTracking/tools/`** (no en `tools/` raíz): garantiza que viaje en el OTA sin modificar la whitelist del `ReleaseBuilder`.
3. **Modus operandi de OTA automático**: registrado como regla en CLAUDE.md para que valga a futuro, no solo para este módulo.

---

## Validación

- ✅ `php -l` OK en los 3 controllers + script de purga.
- ✅ Script de purga testeado con `--dry-run --verbose` en local: output correcto, sin warnings de deprecation (se corrigieron `${var}` → `{$var}` para PHP 8.2+).
- 🔲 **Pendiente manual post-deploy**:
  1. Crear un presupuesto de prueba → verificar fila en `rxn_geo_eventos` con `event_type='presupuesto.created'`.
  2. Idem tratativa y PDS.
  3. Agendar el cron en el server de prod.
  4. Configurar `GOOGLE_MAPS_API_KEY` en `.env` de prod y verificar mapa.

---

## Fin del módulo RxnGeoTracking

| Fase | Release | Estado |
|------|---------|--------|
| 1. Infraestructura | 1.10.0 | ✅ |
| 2. Consentimiento | 1.10.0 | ✅ |
| 3. Auth (login) | 1.10.0 | ✅ |
| 4. Dashboard admin | 1.11.0 | ✅ |
| 5. Transaccionales | 1.12.0 | ✅ |
| 6. Purga | 1.12.0 | ✅ |

Módulo cerrado. Mejoras futuras posibles (fuera del alcance inicial): clustering en el mapa, heatmap, alertas por eventos anómalos, integración con MaxMind self-hosted para no depender de ip-api.com.

---

## Archivos

### Nuevos
- `app/modules/RxnGeoTracking/tools/purge_geo_events.php`
- `docs/logs/2026-04-16_1900_release_1_12_0_rxn_geo_tracking_cierre.md`

### Modificados
- `app/modules/CrmPresupuestos/PresupuestoController.php` (+ tracking en store)
- `app/modules/CrmTratativas/TratativaController.php` (+ tracking en store)
- `app/modules/CrmPedidosServicio/PedidoServicioController.php` (+ tracking en store)
- `app/modules/RxnGeoTracking/MODULE_CONTEXT.md` (estado IMPLEMENTADO + script purga + historial)
- `app/modules/Auth/MODULE_CONTEXT.md` (remove marca "propuesto")
- `app/modules/CrmPresupuestos/MODULE_CONTEXT.md` (idem)
- `app/modules/CrmPedidosServicio/MODULE_CONTEXT.md` (idem)
- `app/modules/CrmTratativas/MODULE_CONTEXT.md` (idem)
- `app/config/version.php` (bump a 1.12.0)
- `CLAUDE.md` (modus operandi de cierre con OTA)
- `.env` (placeholder GOOGLE_MAPS_API_KEY)
