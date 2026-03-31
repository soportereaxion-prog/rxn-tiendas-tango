# Diseno Tecnico - Canvas de Impresion

## Objetivo tecnico

Definir la arquitectura base del sistema de `Definicion de formularios de impresion`, pensado como mecanica transversal de la plataforma.

El primer modulo que lo consumira es `Presupuestos CRM`, pero el motor debe nacer desacoplado del documento puntual.

---

## Nombre tecnico recomendado

- `PrintFormEngine`
- `PrintFormDefinition`
- `PrintTemplateRenderer`

### Convencion

- `canvas` = nombre funcional/UX
- `print form definition` = nombre tecnico/arquitectonico

---

## Decision tecnica principal

El motor de formularios imprimibles debe construirse sobre:

- editor visual de hoja en DOM/HTML;
- persistencia JSON estructurada;
- renderer HTML imprimible;
- assets administrados por la plataforma.

No debe basarse inicialmente en un `<canvas>` bitmap como unica fuente de verdad.

---

## Componentes del sistema

## 1. Definicion de formulario

Entidad que representa el formulario reusable.

Campos conceptuales:

- `id`
- `empresa_id`
- `document_key`
- `nombre`
- `descripcion`
- `estado`
- `version_activa_id`
- `created_at`
- `updated_at`

---

## 2. Version de formulario

Entidad que guarda una version concreta del canvas.

Campos conceptuales:

- `id`
- `form_definition_id`
- `version`
- `page_config_json`
- `objects_json`
- `fonts_json`
- `background_asset_id`
- `notes`
- `created_by`
- `created_at`

---

## 3. Asset de formulario

Repositoria de archivos asociados.

Casos iniciales:

- fondos de hoja
- logos
- imagenes fijas
- fuentes futuras si se habilitan

Campos conceptuales:

- `id`
- `empresa_id`
- `tipo`
- `nombre_original`
- `ruta`
- `mime_type`
- `tamano`
- `metadata_json`
- `created_at`

---

## 4. Registro de variables por documento

Cada `document_key` debe declarar una lista blanca de variables permitidas.

No hace falta persistirlas todas en DB si arrancan como contrato interno del codigo.

Pero el motor debe poder exponerlas al editor como catalogo.

---

## 5. Renderer

Servicio responsable de:

- recibir una version del template;
- recibir el contexto de datos del documento;
- resolver variables;
- construir salida HTML imprimible;
- opcionalmente generar salida PDF en una etapa posterior.

---

## Tablas recomendadas

## `print_form_definitions`

```sql
CREATE TABLE IF NOT EXISTS print_form_definitions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    document_key VARCHAR(80) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255) NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'activo',
    version_activa_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_print_form_empresa_document_key_nombre (empresa_id, document_key, nombre),
    KEY idx_print_form_empresa_document_key (empresa_id, document_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `print_form_versions`

```sql
CREATE TABLE IF NOT EXISTS print_form_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    form_definition_id BIGINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL,
    page_config_json LONGTEXT NOT NULL,
    objects_json LONGTEXT NOT NULL,
    fonts_json LONGTEXT NULL,
    background_asset_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_print_form_version (form_definition_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## `print_form_assets`

```sql
CREATE TABLE IF NOT EXISTS print_form_assets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(30) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    tamano BIGINT UNSIGNED NOT NULL DEFAULT 0,
    metadata_json LONGTEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_print_form_assets_empresa_tipo (empresa_id, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## `document_key`

Cada tipo documental debe tener una clave estable.

Ejemplos:

- `crm_presupuesto`
- `crm_pedido_servicio`
- `ventas_remito`
- `ventas_recibo`

Esta clave une:

- variables disponibles;
- formulario activo;
- renderer correspondiente.

---

## `page_config_json`

Configuracion base de la hoja.

Ejemplo:

```json
{
  "page": {
    "size": "A4",
    "orientation": "portrait",
    "width_mm": 210,
    "height_mm": 297,
    "margin_top_mm": 0,
    "margin_right_mm": 0,
    "margin_bottom_mm": 0,
    "margin_left_mm": 0
  },
  "background": {
    "asset_id": 15,
    "mode": "cover",
    "opacity": 1
  },
  "grid": {
    "enabled": true,
    "step_mm": 2,
    "snap": true
  },
  "defaults": {
    "font_family": "Arial",
    "font_size_pt": 10,
    "color": "#111111"
  }
}
```

---

## `objects_json`

Lista de objetos posicionados sobre la hoja.

Ejemplo minimo:

```json
[
  {
    "id": "obj_1",
    "type": "text",
    "x_mm": 12,
    "y_mm": 18,
    "w_mm": 80,
    "h_mm": 8,
    "z_index": 10,
    "content": "Presupuesto",
    "style": {
      "font_family": "Arial",
      "font_size_pt": 14,
      "font_weight": 700,
      "color": "#111111",
      "align": "left"
    }
  },
  {
    "id": "obj_2",
    "type": "variable",
    "x_mm": 135,
    "y_mm": 18,
    "w_mm": 50,
    "h_mm": 8,
    "z_index": 11,
    "source": "presupuesto.numero",
    "style": {
      "font_family": "Arial",
      "font_size_pt": 12,
      "font_weight": 700,
      "align": "right"
    }
  },
  {
    "id": "obj_3",
    "type": "line",
    "x_mm": 10,
    "y_mm": 28,
    "w_mm": 190,
    "h_mm": 0,
    "z_index": 5,
    "style": {
      "stroke": "#222222",
      "stroke_width_mm": 0.3
    }
  }
]
```

---

## Tipos de objeto y contratos

## `text`

Texto fijo.

Campos:

- `content`
- `style`

## `variable`

Texto dinamico proveniente del registro de variables.

Campos:

- `source`
- `format` opcional
- `fallback` opcional
- `style`

## `image`

Imagen fija no repetitiva.

Campos:

- `asset_id`
- `fit`
- `opacity`

## `line`

Linea simple.

Campos:

- `style.stroke`
- `style.stroke_width_mm`

## `rect`

Caja o bloque de fondo.

Campos:

- `style.stroke`
- `style.fill`
- `style.radius_mm`

---

## Objeto estrategico para etapa siguiente: `table_repeater`

Este sera clave para `Presupuestos CRM`.

Debe soportar:

- `source = items[]`
- columnas declaradas
- alto de fila
- header opcional
- crecimiento vertical
- salto de pagina controlado en etapa posterior

Ejemplo conceptual:

```json
{
  "id": "items_table_1",
  "type": "table_repeater",
  "x_mm": 10,
  "y_mm": 80,
  "w_mm": 190,
  "h_mm": 120,
  "source": "items[]",
  "row_height_mm": 6,
  "columns": [
    { "key": "codigo", "label": "Codigo", "width_mm": 25 },
    { "key": "descripcion", "label": "Descripcion", "width_mm": 95 },
    { "key": "cantidad", "label": "Cant.", "width_mm": 20 },
    { "key": "precio_unitario", "label": "Precio", "width_mm": 25 },
    { "key": "importe", "label": "Importe", "width_mm": 25 }
  ]
}
```

---

## Variables por documento

El motor debe exponer un registro por `document_key`.

### Contrato sugerido

```php
interface PrintVariableRegistryInterface
{
    public function documentKey(): string;

    /** @return array<int, array<string, mixed>> */
    public function variables(): array;
}
```

### Ejemplo para `crm_presupuesto`

- `presupuesto.numero`
- `presupuesto.fecha`
- `cliente.nombre`
- `cliente.documento`
- `empresa.nombre`
- `items[].codigo`
- `items[].descripcion`
- `items[].cantidad`
- `items[].precio_unitario`
- `items[].importe`
- `totales.subtotal`
- `totales.total`

---

## Renderer

Servicio sugerido:

- `PrintFormRenderer`

Responsable de:

1. cargar la version activa del formulario;
2. cargar assets;
3. recibir contexto del documento;
4. resolver variables;
5. convertir objetos a HTML imprimible;
6. exponer preview o salida final.

### Regla

El renderer no debe pedir datos al modulo por su cuenta.

El modulo debe entregarle un contexto ya construido.

---

## Builder de contexto por modulo

Cada modulo que quiera imprimir debe implementar su propio `ContextBuilder`.

Ejemplo:

- `CrmPresupuestoPrintContextBuilder`

Responsable de transformar el documento real en una estructura neutra para el motor.

---

## Editor visual

Pantalla sugerida:

- barra superior con acciones (`guardar`, `guardar como version`, `preview`, `publicar`)
- panel izquierdo con herramientas
- hoja central A4
- panel derecho con propiedades del objeto seleccionado

### Herramientas v1

- seleccionar
- mover
- redimensionar
- texto fijo
- variable
- imagen
- linea
- rectangulo
- fondo
- fuente

### Propiedades v1

- posicion `x`, `y`
- ancho `w`
- alto `h`
- fuente
- tamano
- negrita
- color
- alineacion
- z-index

---

## Fuentes

Para no desmadrar el motor desde el dia 1:

- arrancar con whitelist de fuentes seguras;
- mapearlas a CSS controlado;
- dejar fuentes custom para una etapa posterior de assets tipograficos.

### Sugerencia inicial

- `Arial`
- `Helvetica`
- `Times New Roman`
- `Georgia`
- `Courier New`

---

## Fondo de hoja

El fondo debe ser un asset subido por empresa.

Uso previsto:

- formulario preimpreso escaneado
- membrete
- base grafica institucional

### Regla

El fondo no debe romper la legibilidad de los objetos editables.

Se recomienda permitir:

- opacidad
- escala
- posicion

---

## Seguridad y restricciones

- no permitir HTML arbitrario del usuario;
- no ejecutar scripts;
- no resolver variables fuera del registro blanco;
- sanitizar cualquier texto visible;
- controlar assets permitidos y MIME.

---

## Versionado y trazabilidad

Cuando se use un formulario para imprimir un documento real, conviene registrar:

- `document_key`
- `form_definition_id`
- `form_version_id`
- `document_id`
- `rendered_at`

No es obligatorio para nacer, pero es deseable para auditar historico.

---

## Integracion con Presupuestos CRM

Primer circuito objetivo:

1. `Presupuestos CRM` estabiliza su cabecera y detalle.
2. Se define `document_key = crm_presupuesto`.
3. Se crea su registro de variables.
4. El editor visual usa ese registro.
5. El renderer genera la hoja imprimible del presupuesto.

---

## Roadmap recomendado

## Etapa 1

- documentar estandar;
- crear modelo persistente;
- definir registro de variables;
- crear renderer minimo;
- crear editor con hoja A4, fondo, texto, variable, linea y rectangulo.

## Etapa 2

- agregar `table_repeater` para items;
- preview imprimible mas fiel;
- versionado activo/publicado;
- primer uso real con `crm_presupuesto`.

## Etapa 3

- PDF opcional;
- fuentes custom;
- barcode/QR;
- nuevos documentos de la plataforma.

---

## Decision final recomendada

Si la plataforma va a tener una sola mecanica de impresion, este motor debe declararse desde ahora como infraestructura transversal y no como detalle interno de Presupuestos.

`Presupuestos CRM` sera el primer consumidor, no el dueño del sistema.
