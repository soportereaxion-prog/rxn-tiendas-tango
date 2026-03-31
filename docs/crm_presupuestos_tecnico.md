# Diseno Tecnico - Presupuestos CRM v1

## Objetivo tecnico

Bajar a implementacion concreta el modulo `Presupuestos CRM` definido en `docs/crm_presupuestos_plan.md`.

Esta primera etapa apunta a:

- persistencia local confiable;
- autocompletado comercial sin acoplarse a Tiendas;
- carga de renglones desde articulos CRM;
- calculo de totales en backend;
- base estable para una futura capa de impresion/canvas documental.

---

## Principios tecnicos

1. El navegador nunca debe hablar directo con Connect/Tango.
2. El frontend consulta endpoints internos del modulo.
3. Los endpoints internos resuelven primero sobre cache/base local.
4. La integracion remota solo refresca catalogos de forma controlada.
5. El presupuesto se guarda con snapshots para no romper historicos.
6. No se reutiliza la logica de precios/listas de Tiendas.

---

## Piezas reutilizables del sistema actual

### Ya disponibles

- `crm_clientes` como base local del selector de clientes.
- `crm_articulos` como base local del selector de articulos.
- Catalogos comerciales Connect ya relevados desde `ClienteTangoLookupService` para:
  - condicion de venta
  - lista de precios
  - vendedor
  - transporte
- Depositos ya accesibles desde la capa usada en `EmpresaConfig`.
- Patron de CRUD server-rendered con sugerencias parciales.
- Patron de formulario sabana ya validado en CRM con `Pedidos de Servicio`.

### Lo que falta construir

- persistencia de presupuestos;
- persistencia de renglones;
- cache local unificada para catalogos comerciales de presupuesto;
- logica de autocompletado comercial al seleccionar cliente;
- logica de resolucion de precio unitario por lista.

---

## Tablas propuestas

## 1) `crm_presupuestos`

Tabla cabecera del documento operativo.

```sql
CREATE TABLE IF NOT EXISTS crm_presupuestos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    numero INT UNSIGNED NOT NULL,
    fecha DATETIME NOT NULL,
    cliente_id BIGINT UNSIGNED NOT NULL,
    cliente_nombre_snapshot VARCHAR(255) NOT NULL,
    cliente_documento_snapshot VARCHAR(50) NULL,
    deposito_codigo VARCHAR(50) NULL,
    deposito_nombre_snapshot VARCHAR(255) NULL,
    condicion_codigo VARCHAR(50) NULL,
    condicion_nombre_snapshot VARCHAR(255) NULL,
    condicion_id_interno BIGINT UNSIGNED NULL,
    transporte_codigo VARCHAR(50) NULL,
    transporte_nombre_snapshot VARCHAR(255) NULL,
    transporte_id_interno BIGINT UNSIGNED NULL,
    lista_codigo VARCHAR(50) NULL,
    lista_nombre_snapshot VARCHAR(255) NULL,
    lista_id_interno BIGINT UNSIGNED NULL,
    vendedor_codigo VARCHAR(50) NULL,
    vendedor_nombre_snapshot VARCHAR(255) NULL,
    vendedor_id_interno BIGINT UNSIGNED NULL,
    subtotal DECIMAL(15,2) NOT NULL DEFAULT 0,
    descuento_total DECIMAL(15,2) NOT NULL DEFAULT 0,
    impuestos_total DECIMAL(15,2) NOT NULL DEFAULT 0,
    total DECIMAL(15,2) NOT NULL DEFAULT 0,
    estado VARCHAR(30) NOT NULL DEFAULT 'borrador',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_crm_presupuestos_empresa_numero (empresa_id, numero),
    KEY idx_crm_presupuestos_empresa_fecha (empresa_id, fecha),
    KEY idx_crm_presupuestos_empresa_cliente (empresa_id, cliente_id),
    KEY idx_crm_presupuestos_empresa_estado (empresa_id, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Notas

- `numero` es correlativo por `empresa_id`.
- `estado` en v1 puede arrancar con:
  - `borrador`
  - `emitido`
  - `anulado`
- el cliente queda vinculado por `cliente_id`, pero tambien congelado en snapshot.

---

## 2) `crm_presupuesto_items`

Tabla de renglones del presupuesto.

```sql
CREATE TABLE IF NOT EXISTS crm_presupuesto_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    presupuesto_id BIGINT UNSIGNED NOT NULL,
    empresa_id BIGINT UNSIGNED NOT NULL,
    orden INT UNSIGNED NOT NULL DEFAULT 1,
    articulo_id BIGINT UNSIGNED NULL,
    articulo_codigo VARCHAR(100) NOT NULL,
    articulo_descripcion_snapshot VARCHAR(255) NOT NULL,
    lista_codigo_aplicada VARCHAR(50) NULL,
    cantidad DECIMAL(15,4) NOT NULL DEFAULT 1,
    precio_unitario DECIMAL(15,4) NOT NULL DEFAULT 0,
    bonificacion_porcentaje DECIMAL(7,4) NOT NULL DEFAULT 0,
    importe_bruto DECIMAL(15,2) NOT NULL DEFAULT 0,
    importe_neto DECIMAL(15,2) NOT NULL DEFAULT 0,
    precio_origen VARCHAR(20) NOT NULL DEFAULT 'manual',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_crm_presupuesto_items_presupuesto_orden (presupuesto_id, orden),
    KEY idx_crm_presupuesto_items_empresa_articulo (empresa_id, articulo_id),
    CONSTRAINT fk_crm_presupuesto_items_presupuesto
        FOREIGN KEY (presupuesto_id) REFERENCES crm_presupuestos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Notas

- `precio_origen` permite distinguir:
  - `catalogo`
  - `manual`
  - `fallback`
- `articulo_descripcion_snapshot` queda congelado aunque despues cambie el articulo en CRM.

---

## 3) `crm_catalogo_comercial_items`

Cache local de catalogos comerciales usada por el modulo.

```sql
CREATE TABLE IF NOT EXISTS crm_catalogo_comercial_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    id_interno BIGINT UNSIGNED NULL,
    payload_json LONGTEXT NULL,
    fecha_ultima_sync DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_crm_catalogo_comercial_empresa_tipo_codigo (empresa_id, tipo, codigo),
    KEY idx_crm_catalogo_comercial_empresa_tipo (empresa_id, tipo),
    KEY idx_crm_catalogo_comercial_empresa_tipo_desc (empresa_id, tipo, descripcion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Tipos previstos

- `deposito`
- `condicion_venta`
- `lista_precio`
- `vendedor`
- `transporte`

### Por que esta tabla

Resuelve el ruido de futuro con muchas conexiones:

- el browser no consulta remoto al elegir cliente;
- la UI siempre lee opciones locales;
- si hace falta refrescar catalogos, eso se hace por accion controlada del backend.

---

## 4) Tabla recomendada antes de automatizar precio por lista

Mientras no este relevado el pricing CRM definitivo, el modulo puede vivir con precio manual.

Pero para autocompletar bien el precio por lista, la tabla recomendada es:

### `crm_articulo_precios`

```sql
CREATE TABLE IF NOT EXISTS crm_articulo_precios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    articulo_id BIGINT UNSIGNED NOT NULL,
    lista_codigo VARCHAR(50) NOT NULL,
    precio DECIMAL(15,4) NOT NULL,
    moneda_codigo VARCHAR(10) NULL,
    fecha_ultima_sync DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_crm_articulo_precios_empresa_articulo_lista (empresa_id, articulo_id, lista_codigo),
    KEY idx_crm_articulo_precios_empresa_lista (empresa_id, lista_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Decision operativa

Si esta tabla no existe o no esta poblada todavia:

- el renglon se crea igual;
- `precio_unitario` queda editable/manual;
- `precio_origen = manual` o `fallback`.

Eso permite no bloquear el modulo mientras se releva pricing real.

---

## Rutas propuestas

Siguiendo el patron actual de CRM:

```php
$router->get('/mi-empresa/crm/presupuestos', ... index ...);
$router->get('/mi-empresa/crm/presupuestos/sugerencias', ... suggestions ...);
$router->get('/mi-empresa/crm/presupuestos/crear', ... create ...);
$router->post('/mi-empresa/crm/presupuestos', ... store ...);

$router->get('/mi-empresa/crm/presupuestos/clientes/sugerencias', ... clientSuggestions ...);
$router->get('/mi-empresa/crm/presupuestos/clientes/contexto', ... clientContext ...);

$router->get('/mi-empresa/crm/presupuestos/articulos/sugerencias', ... articleSuggestions ...);
$router->get('/mi-empresa/crm/presupuestos/articulos/contexto', ... articleContext ...);

$router->get('/mi-empresa/crm/presupuestos/{id}/editar', ... edit ...);
$router->post('/mi-empresa/crm/presupuestos/{id}', ... update ...);
```

### Rutas diferidas

Para no arrancar por el final, quedan fuera de v1:

- `/imprimir`
- `/duplicar-version`
- `/pasar-a-pedido`
- `/enviar-mail`
- `/enviar-whatsapp`

---

## Capas y archivos sugeridos

## Modulo principal

- `app/modules/CrmPresupuestos/PresupuestoController.php`
- `app/modules/CrmPresupuestos/PresupuestoRepository.php`
- `app/modules/CrmPresupuestos/views/index.php`
- `app/modules/CrmPresupuestos/views/form.php`
- `public/js/crm-presupuestos-form.js`

## Soporte comercial local

- `app/modules/CrmPresupuestos/CommercialCatalogRepository.php`

### Opcional si se quiere aislar mejor la logica

- `app/modules/CrmPresupuestos/PresupuestoTotalsService.php`

En v1 no es obligatorio crear demasiados servicios. Si el controlador se mantiene legible, alcanza con:

- `PresupuestoController`
- `PresupuestoRepository`
- `CommercialCatalogRepository`

---

## Responsabilidades por clase

## `PresupuestoController`

Responsable de:

- renderizar listado;
- renderizar alta/edicion;
- validar request;
- pedir defaults comerciales al elegir cliente;
- pedir contexto de articulo al agregar renglon;
- calcular totales en backend antes de persistir;
- delegar persistencia al repositorio.

### Metodos sugeridos

- `index()`
- `suggestions()`
- `create()`
- `store()`
- `edit(string $id)`
- `update(string $id)`
- `clientSuggestions()`
- `clientContext()`
- `articleSuggestions()`
- `articleContext()`
- `buildUiContext()`
- `defaultFormState()`
- `hydrateFormState()`
- `buildFormStateFromPost()`
- `validateRequest()`
- `normalizeSearchField()`
- `calculateTotals(array $items)`

---

## `PresupuestoRepository`

Responsable de:

- bootstrap de tablas;
- correlativo por empresa;
- persistir cabecera;
- persistir renglones;
- lectura de listado;
- lectura de detalle;
- reemplazo atomico de items en update.

### Metodos sugeridos

- `previewNextNumero(int $empresaId): int`
- `countAll(int $empresaId, string $search = '', string $field = 'all', string $estado = ''): int`
- `findAllPaginated(...)`
- `findSuggestions(...)`
- `findById(int $id, int $empresaId): ?array`
- `findItemsByPresupuestoId(int $presupuestoId, int $empresaId): array`
- `create(array $payload): int`
- `update(int $id, int $empresaId, array $payload): void`
- `replaceItems(int $presupuestoId, int $empresaId, array $items): void`
- `ensureSchema(): void`

### Transaccion en guardado

Tanto `create()` como `update()` deben correr en transaccion:

1. guardar cabecera;
2. guardar o reemplazar items;
3. commit;
4. rollback completo si algo falla.

---

## `CommercialCatalogRepository`

Responsable de:

- leer catalogos locales por tipo;
- resolver codigo + descripcion + id interno;
- upsert de catalogos sincronizados.

### Metodos sugeridos

- `findAllByType(int $empresaId, string $tipo): array`
- `findOption(int $empresaId, string $tipo, string $codigo): ?array`
- `upsertMany(int $empresaId, string $tipo, array $items): void`

---

## Flujo exacto de alta

## 1. GET `/mi-empresa/crm/presupuestos/crear`

El controlador arma el formulario con:

- `numero` sugerido desde `previewNextNumero()`;
- `fecha` actual;
- cliente vacio;
- deposito default si existe en config CRM;
- combos comerciales cargados desde `crm_catalogo_comercial_items`;
- cuerpo vacio sin renglones.

### Importante

No debe disparar una consulta remota al abrir la pantalla.

---

## 2. Selector de cliente

El operador escribe nombre/documento/codigo.

Frontend:

- pega a `/mi-empresa/crm/presupuestos/clientes/sugerencias`;
- busca solo sobre `crm_clientes`.

Al seleccionar cliente:

- frontend llama a `/mi-empresa/crm/presupuestos/clientes/contexto?id=...`;
- backend responde con:
  - `cliente_id`
  - `cliente_nombre`
  - `cliente_documento`
  - `condicion_*`
  - `transporte_*`
  - `lista_*`
  - `vendedor_*`

La respuesta se arma desde:

1. `crm_clientes` para codigos/defaults del cliente;
2. `crm_catalogo_comercial_items` para traducir codigos a descripcion e ID interno.

---

## 3. Override manual de cabecera

Aunque el cliente autocompleta, el operador puede cambiar manualmente:

- deposito
- condicion
- transporte
- lista
- vendedor

La UI debe tomar siempre sus opciones desde cache local, no desde remoto interactivo.

---

## 4. Selector de articulo

El operador escribe codigo o descripcion.

Frontend:

- consulta `/mi-empresa/crm/presupuestos/articulos/sugerencias`;
- busca solo sobre `crm_articulos`.

Al seleccionar articulo:

- frontend llama a `/mi-empresa/crm/presupuestos/articulos/contexto?id=...&lista=...`;
- backend devuelve:
  - `articulo_id`
  - `articulo_codigo`
  - `articulo_descripcion`
  - `precio_unitario`
  - `precio_origen`

### Regla de precio

Orden recomendado:

1. buscar precio por `articulo + lista` en cache local;
2. si no existe, devolver `null` y dejar edicion manual;
3. nunca buscar remoto en caliente por cada articulo tipeado.

---

## 5. Renglon en pantalla

Al resolver el articulo, frontend agrega una fila editable con inputs array:

```php
items[0][articulo_id]
items[0][articulo_codigo]
items[0][articulo_descripcion]
items[0][cantidad]
items[0][precio_unitario]
items[0][bonificacion_porcentaje]
items[0][importe_neto]
items[0][precio_origen]
```

### Motivo de esta decision

- evita inventar un mini motor JSON si no hace falta;
- PHP recibe arrays nativos por `$_POST['items']`;
- el backend valida renglones sin transformaciones extra raras.

---

## 6. Submit del formulario

Al grabar:

1. backend valida cabecera requerida;
2. valida que exista al menos un item;
3. recalcula importes y totales server-side;
4. crea cabecera;
5. crea renglones;
6. redirige a editar.

### Validaciones minimas

- `fecha` obligatoria;
- `cliente_id` obligatorio;
- `lista_codigo` obligatoria;
- `items` no vacio;
- cada item con `articulo_codigo`, `articulo_descripcion`, `cantidad > 0`;
- `precio_unitario >= 0`;
- `bonificacion_porcentaje` entre `0` y `100`.

---

## Formula recomendada de calculo

Por item:

```text
importe_bruto = cantidad * precio_unitario
importe_neto = importe_bruto - (importe_bruto * bonificacion_porcentaje / 100)
```

Cabecera:

```text
subtotal = suma de importes_brutos
descuento_total = suma de (importe_bruto - importe_neto)
impuestos_total = 0 en v1
total = suma de importes_netos
```

Aunque el frontend muestre calculos en vivo, el valor final valido siempre debe recalcularse en backend.

---

## Flujo de edicion

## GET `/mi-empresa/crm/presupuestos/{id}/editar`

Debe cargar:

- cabecera snapshot guardada;
- items existentes ordenados;
- catalogos comerciales locales para mostrar labels en combos;
- posibilidad de agregar/quitar renglones.

## POST `/mi-empresa/crm/presupuestos/{id}`

Debe:

- actualizar cabecera;
- borrar/reemplazar items dentro de una transaccion;
- recalcular totales;
- guardar `updated_at`.

---

## Busqueda del listado

Campos sugeridos para `search`:

- `numero`
- `cliente`
- `fecha`
- `estado`

No hace falta inventar un buscador distinto: debe seguir el estandar ya documentado en `docs/modules.md`.

---

## Decisiones importantes de esta etapa

1. **No usar carrito**
   - el cuerpo son renglones editables del presupuesto.

2. **No usar precios de Tiendas**
   - `precio_lista_1` / `precio_lista_2` no forman el contrato del modulo.

3. **No llamar remoto desde el browser**
   - todo pasa por endpoints internos y cache local.

4. **No abrir aun versionado documental**
   - `Nueva Version` se posterga hasta estabilizar presupuesto simple.

5. **No imprimir en v1**
   - la capa A4/Crystal/canvas vendra despues del modulo base.

---

## Orden de implementacion recomendado

1. crear `PresupuestoRepository` con bootstrap de tablas;
2. crear rutas base del modulo;
3. crear listado vacio + alta/edicion;
4. resolver selector de cliente con defaults comerciales;
5. resolver selector de articulo y tabla de renglones;
6. guardar cabecera + items + totales;
7. recien despues atacar pricing por lista mas fino y capa documental.

La capa documental futura debe alinearse con:

- `docs/print_forms_canvas_standard.md`
- `docs/print_forms_canvas_tecnico.md`
