# Release 1.46.3 — Auditoría de eliminación de PDS

**Fecha**: 2026-05-05 23:30
**Tipo**: Feature defensivo (preventivo + forense)
**Origen**: Incidente del PDS X0065400007931 investigado en la misma sesión

---

## El cuento corto

Charly perdió el PDS X0065400007931 — lo había creado, pusheado a Tango (que le asignó el número), y borrado desde la papelera de RXN. Quedó huérfano en Tango sin ningún rastro en RXN. Cero log, cero tabla audit, cero trace. El módulo de sincronización Tango↔RXN ni siquiera cubre PDS (es one-way RXN→Tango). Caso que se resolvió a fuerza de archeology en snapshots y deducción.

Para que **NO vuelva a pasar**, implementamos auditoría de eliminación permanente con red de seguridad triple-capa.

---

## Arquitectura

### 1) Tabla `crm_pedidos_servicio_audit_deletes`

Captura todos los campos clave del PDS al momento del borrado + snapshot completo en JSON + atribución:

```sql
CREATE TABLE crm_pedidos_servicio_audit_deletes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,           -- ID original del PDS borrado
    empresa_id INT NOT NULL,
    numero VARCHAR(50),
    cliente_id INT,
    cliente_nombre VARCHAR(255),
    fecha_inicio DATETIME,
    fecha_finalizado DATETIME,
    tango_nro_pedido VARCHAR(50),     -- ← clave: si != NULL, quedó huérfano en Tango
    tango_estado INT,
    usuario_id INT,
    usuario_nombre VARCHAR(255),
    diagnostico TEXT,
    solicito VARCHAR(255),
    before_json LONGTEXT,             -- snapshot completo (JSON_OBJECT de todas las columnas)
    deleted_by INT,                   -- @audit_user_id seteado por el repository
    deleted_by_nombre VARCHAR(255),   -- @audit_user_name
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_empresa (empresa_id),
    INDEX idx_audit_deleted_at (deleted_at),
    INDEX idx_audit_tango_nro (tango_nro_pedido),
    INDEX idx_audit_pedido_id (pedido_id)
)
```

### 2) Trigger SQL `BEFORE DELETE`

```sql
CREATE TRIGGER tr_crm_pds_audit_before_delete
BEFORE DELETE ON crm_pedidos_servicio
FOR EACH ROW
INSERT INTO crm_pedidos_servicio_audit_deletes (...)
VALUES (OLD.id, OLD.empresa_id, ..., JSON_OBJECT(...), @audit_user_id, @audit_user_name)
```

**Por qué trigger SQL y no solo PHP**: captura DELETEs hechos desde phpMyAdmin, HeidiSQL, scripts SQL manuales o cualquier herramienta externa. Si alguien evita el código de aplicación, el trigger igual loguea.

### 3) Repository setea `@audit_user_id` antes del DELETE

```php
$userId = $_SESSION['user_id'] ?? null;
$userName = $_SESSION['user_name'] ?? null;
$this->db->exec('SET @audit_user_id = ' . ($userId ?? 'NULL'));
$this->db->exec('SET @audit_user_name = ' . ($userName ? $this->db->quote($userName) : 'NULL'));
// luego DELETE FROM crm_pedidos_servicio WHERE ...
```

Cuando el trigger ejecuta, lee esas variables de sesión MySQL y las loggea como atribución. Si el delete viene desde fuera del repository (sin sesión), las vars vienen NULL y el audit lo registra como NULL → eso señaliza "delete no atribuible" sin perder el snapshot.

### 4) Vista SQL `RXN_LIVE_VW_PDS_DELETES`

Resuelve códigos numéricos a labels legibles y agrega un flag calculado clave:

```sql
CASE WHEN a.tango_nro_pedido IS NOT NULL AND a.tango_nro_pedido <> ''
     THEN 'Sí — quedó huérfano en Tango'
     ELSE 'No'
END AS estaba_en_tango
```

Aplicación práctica: filtrá por `estaba_en_tango = "Sí"` y ves TODOS los huérfanos pendientes de anular en el ERP.

### 5) Dataset registrado en RxnLive

`RxnLiveService::$datasets['pds_eliminados']` con `pivot_metadata` completo. Aparece automáticamente en el menú de datasets del módulo RxnLive.

---

## Validación end-to-end

Smoke test SQL:

```
=== 1) Verificar tabla, trigger y vista creados ===
tabla    1
vista    1
trigger  1

=== 2) Test del trigger: insertar PDS dummy, borrarlo, verificar audit ===
pds_id_creado: 110
audit capturó:
  pedido_id: 110
  numero: 99999
  tango_nro_pedido: X9999999999999
  deleted_by: 999
  deleted_by_nombre: TEST USER (smoke)
  before_json: {...completo, incluye diagnostico...}

=== 3) Vista RXN_LIVE_VW_PDS_DELETES ===
  tango_estado_label: "Aprobado"
  estaba_en_tango: "Sí — quedó huérfano en Tango"  ← bandera roja correcta
```

Test exitoso en los 3 niveles: tabla creada, trigger captura, vista resuelve labels.

---

## Decisiones tomadas

- **Tabla específica por módulo (`crm_pedidos_servicio_audit_deletes`) en lugar de tabla genérica `crm_audit_deletes`**: más explícita, vistas RxnLive más simples, índices más ajustados al uso. Si en el futuro auditamos 5+ módulos y queremos cross-search, evaluamos consolidar.
- **Trigger SQL en lugar de solo lógica PHP**: red de seguridad más amplia. PHP sigue seteando atribución, el trigger garantiza que el evento de borrado nunca se pierde.
- **`before_json` con TODOS los campos**: aunque tenemos columnas tipadas para los campos clave (numero, cliente, tango_nro_pedido, etc), el JSON garantiza que cualquier campo del PDS queda capturado — incluyendo campos nuevos que se sumen al schema en futuro sin actualizar el trigger.
- **Vista calcula `estaba_en_tango` legible**: el operador no técnico que mira RxnLive entiende inmediatamente "Sí — quedó huérfano en Tango" mejor que `tango_nro_pedido != NULL`.

---

## Pendiente (próximas sesiones)

- 🔲 Replicar el patrón en `CrmPresupuestos` — Charly lo pidió explícito al cierre. Mismo diseño: tabla audit + trigger + vista + dataset RxnLive.
- 🔲 Considerar UX preventiva: modal de confirmación cuando se intenta hard-delete un PDS con `tango_nro_pedido != NULL` ("este PDS ya está en Tango con número X. Si lo borrás de RXN, queda huérfano allá. ¿Anular en Tango también?").
- 🔲 A futuro extendible: Tratativas, Clientes, Llamadas. Patrón ya validado.

---

## Relevant Files

- `database/migrations/2026_05_05_02_create_crm_pds_audit_deletes.php` — tabla + trigger + vista (idempotente).
- `app/modules/CrmPedidosServicio/PedidoServicioRepository.php` — `forceDeleteByIds` setea `@audit_user_id` y `@audit_user_name`.
- `app/modules/RxnLive/RxnLiveService.php` — nuevo dataset `pds_eliminados`.
- `database/migrations/2026_05_05_03_seed_customer_notes_release_1_46_3.php` — seed nota visible al cliente.
- `app/config/version.php` — bump 1.46.2 → 1.46.3 / build 20260505.3.
- `docs/logs/2026-05-05_2330_release_1_46_3_audit_deletes_pds.md` — este archivo.
