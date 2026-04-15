# Release 1.6.1 — CRM Mail Masivos: fix cancel idempotente + reactivar + destrabar zombies

## Fecha y tema
2026-04-15 04:00 — Hotfix del módulo CrmMailMasivos (v1.6.0). Tres issues descubiertos al probar en producción.

## Problemas detectados en prod

### Issue 1: URL pública del CRM mal hardcodeada
En el v1.6.0, el workflow n8n "CRM Mail Masivos — Dispatcher" llevaba hardcodeado `https://suite.reaxion.com.ar` en el Code node. El dominio real es `https://suite.reaxionsoluciones.com.ar`. Consecuencia: n8n recibía el webhook del CRM, intentaba devolver las llamadas a `process-batch`, y fallaba porque no resolvía el dominio. El job quedaba en queued sin progreso.

**Causa raíz**: asumí el dominio desde contexto parcial en sesiones previas en vez de preguntar.

### Issue 2: Cancel dejaba jobs "zombies" cuando n8n no arrancaba
Si Charly clickeaba "Cancelar" en un job que todavía estaba en `queued` (por el Issue 1), el botón solo seteaba `cancel_flag=1`, pero el job seguía en `queued`. El `BatchProcessor` NUNCA arrancaba (porque n8n no pudo llegar al CRM), entonces el flag jamás se chequeaba, y el job quedaba indefinidamente como "En cola + cancelación solicitada".

**Causa raíz**: `cancel_flag` estaba diseñado solo para mid-flight (running → cortar en próximo batch). No había path para el caso queued-nunca-arrancó.

### Issue 3: No había forma de reactivar jobs cancelled/failed
Una vez que un job se cerraba (cancelled o failed), no había manera de volver a intentarlo. Había que crear uno nuevo desde cero.

## Qué se hizo

### 1. Workflow n8n corregido
- Update del Code node del workflow via `mcp__n8n__update_workflow`: `crmBaseUrl = 'https://suite.reaxionsoluciones.com.ar'`.
- Workflow republicado (activeVersionId `61c8e147-88fe-4a3a-be95-f64300a2b6c9`).
- Log de release 1.6.0 corregido: URL actualizada en el `.env` example.

### 2. Cancel idempotente según estado del job
- `JobRepository::closeQueuedAsCancelled(jobId, empresaId, reason)`: cierre directo. Si el job está en `queued`, marca todos los items pending como skipped con reason, incrementa `total_skipped`, pasa el job a `cancelled` con `finished_at=NOW()` y `mensaje_error=reason`. Es idempotente (solo actúa si estado=queued).
- `JobController::cancel()` modificado:
  - Si estado=`queued` → `closeQueuedAsCancelled` inmediato.
  - Si estado=`running`/`paused` → `setCancelFlag` (BatchProcessor corta en próximo batch).
  - Si estado final → flash warning sin action.

### 3. Botón Reactivar para jobs cancelled/failed
- `JobRepository::reactivate(jobId, empresaId)`: resetea el job si el estado es `cancelled` o `failed`. Vuelve a `pending` los items skipped cuyo `error_msg` empieza con "Cancelado" o "Destrabado" (los demás skipped — por email inválido, por ejemplo — quedan skipped permanente). Resetea `total_skipped` restando los reactivados. Pone estado en `queued`, `cancel_flag=0`, `started_at`/`finished_at`/`mensaje_error` en NULL.
- `JobController::reactivate(id)`: llama `repo.reactivate` + re-dispara webhook a n8n via reflection sobre `JobDispatcher::triggerN8nWebhook`. Si el webhook falla, el job queda en queued y Charly puede correr el CLI worker.
- Vista monitor.php: botón **"Reactivar envío"** visible cuando el estado es cancelled o failed.
- Vista monitor.php: botón **"Destrabar y cerrar"** (icono `bi-x-octagon-fill`) cuando detecta zombie queued+cancel_flag=1 — semánticamente más claro que "Cancelando...".

### 4. Tool CLI para destrabar zombies pre-existentes en prod
- `tools/destrabar_jobs_zombies.php [empresa_id]` — usa `JobRepository::destrabarZombies` para limpiar en batch todos los jobs zombies. Pensado para correr UNA VEZ después del deploy del 1.6.1.
- `JobRepository::destrabarZombies(empresaId=0, reason)` — detecta todos los queued+cancel_flag=1 y los cierra con `closeQueuedAsCancelled`. Devuelve count.

### 5. Ruta nueva
- `POST /mi-empresa/crm/mail-masivos/envios/{id}/reactivar` con guard `requireCrm`.

## Por qué

- El flujo n8n → CRM depende de una URL pública correcta. Si está mal, TODOS los envíos quedan zombies. Había que:
  - Arreglar la URL del workflow (causa raíz).
  - Dar una salida de emergencia al usuario sin tener que entrar a la DB (botón "Destrabar y cerrar").
  - Permitir recuperar los envíos una vez arreglada la infra (botón "Reactivar").
- "Cancelar" como botón tiene que ser idempotente y obvio: independientemente del estado interno, clickear Cancelar debe producir un cierre rápido y visible.
- Reactivar es una feature útil por sí misma: si un envío falla masivamente por SMTP caído, Charly puede re-dispararlo después de arreglar el SMTP sin perder la configuración ni los items.

## Impacto

- Sin cambios de schema — ningún ALTER TABLE. Todo en código PHP.
- 1 ruta nueva (+ la de reactivar).
- 1 tool CLI nuevo (`destrabar_jobs_zombies.php`).
- Workflow n8n actualizado (ya publicado en hstgr.cloud).

## Validación

- Lint PHP OK en JobRepository, JobController, views/envios/monitor.php, tools/destrabar_jobs_zombies.php.
- Local: verificado que no hay zombies activos (ya corrimos el tool de destrabe y 0 jobs afectados).

## Pendiente para Charly

1. Subir OTA del 1.6.1 a prod.
2. Correr **una vez** en prod: `php tools/destrabar_jobs_zombies.php` — va a destrabar los 3 jobs zombies que aparecen en el screenshot del UI.
3. Disparar un envío nuevo — ahora n8n ya tiene la URL correcta, el flujo debería completarse.
4. Si por algún motivo el nuevo envío queda zombie (n8n caído, timeout, etc.), Charly puede:
   - Clickear "Destrabar y cerrar" desde el monitor para cerrar el job.
   - O correr `php tools/process_mail_job.php <job_id>` como fallback.
   - O clickear "Reactivar envío" después para intentar de nuevo.
