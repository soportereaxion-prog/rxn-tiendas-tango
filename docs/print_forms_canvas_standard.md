# Estandar de Formularios de Impresion

## Nombre funcional

En producto, esta pieza se nombra:

- `Canvas de impresion`
- o `Definicion de formularios de impresion`

Ambos nombres refieren a la misma mecanica transversal de la plataforma.

---

## Objetivo

Definir una mecanica unica para disenar, versionar y renderizar formularios imprimibles dentro del sistema.

La primera necesidad visible nace en `Presupuestos CRM`, pero el estandar debe servir tambien para futuros:

- pedidos
- remitos
- recibos
- ordenes de servicio
- comprobantes internos

---

## Criterio de producto

La experiencia para el usuario debe sentirse como un `canvas` sobre una hoja real.

Eso implica que el operador pueda:

- elegir tamano de hoja (`A4` primero);
- definir orientacion (`vertical` / `horizontal`);
- colocar una imagen de fondo;
- escribir texto fijo;
- insertar variables del sistema;
- elegir fuente, tamano, color y alineacion;
- dibujar encima del formulario;
- guardar versiones del formulario;
- previsualizar la impresion final.

---

## Criterio tecnico

Aunque funcionalmente se lo llame `canvas`, la implementacion base recomendada para la plataforma no debe depender de un `<canvas>` raster como soporte principal de impresion.

La base tecnica recomendada es:

- hoja editable sobre DOM/HTML;
- objetos posicionados por coordenadas;
- render imprimible en HTML/CSS;
- salida futura a PDF si hace falta.

### Por que

- imprime mejor texto y tablas;
- conserva calidad tipografica;
- facilita variables dinamicas y bloques repetitivos;
- evita pelearse demasiado pronto con un motor bitmap.

En terminos simples:

- **para el usuario es un canvas**;
- **para el motor es una hoja DOM editable**.

---

## Alcance del estandar v1

La primera version del sistema de formularios debe soportar:

1. hoja `A4`;
2. orientacion vertical u horizontal;
3. fondo por imagen;
4. objetos posicionables;
5. texto fijo;
6. variable simple;
7. imagen fija;
8. linea;
9. rectangulo;
10. selector de fuente desde whitelist;
11. versionado del formulario;
12. previsualizacion de impresion.

### Diferido

Queda fuera del v1:

- formulas libres escritas por usuario;
- scripting custom;
- editor WYSIWYG HTML libre;
- PDF nativo obligatorio;
- bloques anidados complejos;
- freehand artistico sin control;
- barcode / QR como requisito inicial.

---

## Objetos base del canvas

Todo formulario de impresion se compone por objetos.

### Objetos obligatorios del estandar

- `text`
- `variable`
- `image`
- `line`
- `rect`
- `group` (solo para organizacion visual si hace falta)

### Objetos previstos para etapa siguiente

- `table_repeater`
- `rich_text_block`
- `ellipse`
- `path`
- `barcode`
- `qrcode`

---

## Variables

Las variables del sistema no se escriben libremente como cualquier string inventado.

Se trabajan contra un registro controlado.

### Tipos de variables

#### Simples
- `empresa.nombre`
- `empresa.cuit`
- `cliente.nombre`
- `cliente.documento`
- `presupuesto.numero`
- `presupuesto.fecha`

#### Calculadas
- `totales.subtotal`
- `totales.descuento`
- `totales.total`

#### Repetitivas
- `items[].codigo`
- `items[].descripcion`
- `items[].cantidad`
- `items[].precio_unitario`
- `items[].importe`

### Regla

El editor solo puede ofrecer variables registradas por modulo/documento.

No se permiten placeholders arbitrarios sin definicion previa.

---

## Estructura conceptual del formulario

Un formulario imprimible se define por:

1. `document_key`
2. `template`
3. `version`
4. `page setup`
5. `objects`
6. `variable registry`

### Definiciones

- `document_key`: identifica el tipo de documento (`crm_presupuesto`, por ejemplo)
- `template`: formulario editable reusable
- `version`: snapshot de una version concreta del template
- `page setup`: hoja, margenes, fondo y reglas de base
- `objects`: elementos dibujados/colocados sobre la hoja
- `variable registry`: lista blanca de variables que el formulario puede usar

---

## Reglas de diseno

### Unidades
- usar `mm` como unidad canonica del diseno;
- coordenadas internas siempre expresadas en `x`, `y`, `w`, `h`.

### Hoja
- `A4` es la primera hoja soportada;
- luego pueden agregarse `A5`, `Letter` o formularios custom.

### Capas
- cada objeto tiene `z_index`;
- el fondo vive por debajo de todo;
- los objetos se dibujan por capa.

### Fuentes
- la plataforma debe arrancar con una whitelist controlada;
- no se deben habilitar fuentes arbitrarias desde el navegador sin estrategia de assets.

### Fondo
- el fondo debe tratarse como asset del formulario y no como simple URL suelta.

---

## Dibujo encima del formulario

Cuando el usuario dice "dibujar arriba", el estandar interpreta esto como herramientas graficas controladas sobre la hoja:

- linea
- rectangulo
- marco
- bloque de color
- imagen/logo
- texto

### Nota importante

El dibujo libre tipo lapiz puede existir en el futuro, pero no debe ser base del sistema.

Para impresion corporativa, primero importa resolver bien:

- posicionamiento exacto;
- tipografia;
- fondo;
- variables;
- repeticion de datos.

---

## Versionado

Todo formulario de impresion debe versionarse.

### Regla minima

- una definicion puede tener varias versiones;
- solo una puede estar activa por documento y empresa;
- al imprimir un documento real, debe registrarse que version del template se uso.

Esto evita que un cambio posterior rompa impresiones historicas.

---

## Integracion con modulos

Cada modulo que quiera imprimir debe declarar:

- su `document_key`;
- su registro de variables;
- el contexto de datos que entrega al renderer.

### Primer consumidor previsto

- `CRM / Presupuestos`

Despues pueden colgarse otros documentos sin cambiar la mecanica base.

---

## Regla de render

El flujo correcto es:

1. el modulo genera el contexto de datos;
2. el sistema de formularios resuelve el template activo;
3. se reemplazan variables admitidas;
4. se renderiza HTML imprimible;
5. opcionalmente se exporta a PDF en una etapa posterior.

---

## Regla de persistencia

Un formulario no debe guardarse como HTML suelto solamente.

Debe existir una definicion estructurada persistible, editable y versionable.

Eso implica guardar:

- setup de pagina;
- fondo;
- fuentes/config base;
- lista de objetos;
- metadata de version.

---

## Regla de evolucion

La plataforma debe crecer agregando variables y objetos, no reinventando un sistema de impresion distinto por cada modulo.

Toda necesidad nueva debe intentar entrar por esta misma mecanica de `Definicion de formularios de impresion`.

---

## Resultado esperado

Si este estandar se respeta, el sistema gana:

- una sola mecanica de impresion;
- documentos configurables por empresa;
- base limpia para Crystal-like sin meter CrystalReports;
- posibilidad real de crecer hacia PDF, preview avanzada y versionado documental.
