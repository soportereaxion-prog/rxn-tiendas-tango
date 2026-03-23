# [Artículos] — [Sincronización de Stock y Paginación Mejorada]

### 🧠 Lectura rápida
Se implementó dentro del módulo de Artículos la integración con Tango Connect (Process 17668) para asimilar los Saldos de Stock. Adicionalmente, se robusteció la matriz de configuración global por empresa anexando selección prioritaria de depósito mediante su codificación y se superpotenció el FrontEnd con un Paginador con Límites Selectivos (25/50/100).

### 🔍 Auditoría inicial
Previo a cualquier cirugía estructural, se analizó `EmpresaConfigRepository` y los Controladores Paginados observando un esquema simplista a `$limit=50` empotrado. El cruce con Tango requería entender exactamente de qué forma bajaban los inventarios para enrostrar el campo correspondiente a la Persistencia Local.

### 📦 Payload real de stock (Process 17668)
Se ejecutó una sonda (`test_stock.php`) directa hacia `https://<url>/Api/GetApiLiveQueryData?process=17668`. 
Campos estructurales dominantes revelados en el JSON:
- `COD_ARTICULO`: Es el SKU que anudaremos a local `codigo_externo` (Curiosamente llamado distinto que en Process 87, que es `COD_STA11`).
- `ID_STA22`: Id Numérico o String numérico corto dictaminando el depósito de origen del saldo (Ej. `"1"`). No expone Cód Alfanumérico, pero el ID sirve tácitamente como código paramétrico.
- `SALDO_CONTROL_STOCK`: Decimal que arroja el Float real de Tango.
- `DESCRIPCION_DEPOSITO`: Nombre String (Ej: `"DEPOSITO A - ART.DE LINEA"`).

### 🧩 Decisión de diseño
Se procedió mediante el camino **OPCIÓN A** (Campo Directo en Artículos).
**Justificación**: La orden es sincar únicamente el stock del depósito configurado (Max 2 Chars, en este caso el `ID_STA22` base). Introducir una tabla pivote `articulos_stock`, sumado a una Foreign Key compuesta, resultaba en sobreingeniería que desbalancearía el módulo legando una Deuda Técnica para el motor CRUD frontal que actualmente solo lee registros chatos lineales (Precio_1, Precio_2). 

### 🛠️ Implementación
1. `db_alter_stock.php` generó una capa Delta en `empresa_config` (añadiendo `deposito_codigo`) y en `articulos` (añadiendo `stock_actual`).
2. `EmpresaConfigRepository` y `views/index.php` fueron inyectados en cascada para asimilar Input/Output del Setup.
3. Se anexaron al proxy `TangoApiClient` y al orquestador `TangoSyncService` los flujos explícitos `getStock` y `syncStock()`.
4. El cruce es Idempotente y Defensivamente Validado en Hex/Padded String, filtrando todo lo que caiga fuera de `$config->deposito_codigo === $item['ID_STA22']`.
5. Se reprogramó la vista `Articulos/views/index.php` para incorporar Columnas, Paginación controlada (GET param limit) y accionadores de Sync.

### 🧪 Validación
- La inyección de código a `Empresa` procesó correctamente la persistencia del límite String(2).
- El escáner base reportó ~777 registros y los filtró exactamente mediante Condicional Fuerte hacia los saldos locales de SKU sin sufrir transmutaciones (Iden a Iteraciones previas).

### ⚠️ Riesgos / observaciones
- Al no ser Relacional el Almacén, todo salto a Multi-Sucursal masiva futura demandará Mover `stock_actual` hacia una tabla de agregación Pivote `[articulo_id | deposito_id | stock]`. De momento, satisface perfecto la regla de Negocio Unitario.
- Los depósitos deben machear en Configuración usando su `ID_STA22` Tango (Ej: 1, 2) dado que la vista de Connect NO trae expuesto el Alias alfanumérico.

### 📘 Documentación
Esta misma bitácora fue insertada en `/docs/logs/2026-03-23_06-38_sync_stock_articulos.md`.

### 💾 Git
- Archivos Indexados y Commiteados bajo la etiqueta requerida (`feat: sync de stock en articulos`).
- Subido automáticamente a `origin/main`.
