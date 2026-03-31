# Plan Inicial - Presupuestos CRM

## Objetivo

Definir el modulo `Presupuestos CRM` antes de entrar en impresion, canvas A4 o documentos configurables.

La prioridad de esta primera vuelta es cerrar:

- que datos forman la cabecera;
- como se arma el cuerpo;
- de donde sale cada selector comercial;
- que queda persistido localmente;
- que se difiere para iteraciones posteriores.

---

## Criterio rector

`Presupuestos CRM` debe seguir el mismo criterio `local-first` ya adoptado por el entorno CRM:

- la pantalla opera primero sobre base local;
- los datos comerciales externos se consumen por integracion de forma controlada;
- al guardar, el presupuesto conserva snapshots suficientes para no romper historicos;
- no se mezcla la logica comercial de Tiendas con la de CRM.

Importante:

- la `Lista de precios` del presupuesto **no** usa la logica actual de `precio_lista_1` / `precio_lista_2` de Tiendas;
- esa lista debe salir de catalogos comerciales del endpoint/integracion activa;
- el relevamiento definitivo de articulos CRM todavia esta pendiente, por lo que el pricing automatico debe diseñarse sin asumir la estructura de Tiendas.

---

## Alcance funcional v1

El modulo `Presupuestos CRM` en su primera iteracion debe cubrir:

1. Crear presupuesto.
2. Editar presupuesto.
3. Listar presupuestos.
4. Seleccionar cliente y autocompletar cabecera comercial.
5. Buscar articulos por codigo o descripcion.
6. Acumular renglones en el cuerpo del presupuesto.
7. Calcular subtotales y total.
8. Guardar snapshot de cabecera y renglones.

Queda afuera de esta primera vuelta:

- impresion final tipo Crystal;
- canvas A4 configurador;
- PDF;
- envio por mail o WhatsApp;
- pasar a pedido;
- generar pedido de servicio desde presupuesto;
- versionado comercial avanzado (`Nueva Version`) si antes no esta firme el modulo base.

---

## Cabecera requerida

Campos confirmados para la cabecera del presupuesto:

- `Fecha`
- `Cliente`
- `Deposito`
- `Condicion de venta`
- `Transporte`
- `Lista de precios`
- `Vendedor`

### Regla operativa

Al elegir `Cliente`, el sistema debe autocompletar en lo posible:

- `Condicion de venta`
- `Transporte`
- `Lista de precios`
- `Vendedor`

Esto debe salir del circuito comercial de CRM y no de Tiendas.

---

## Cuerpo del presupuesto

El cuerpo se arma por renglones acumulados.

Flujo esperado:

1. El operador busca articulo por `codigo` o `descripcion`.
2. Selecciona un articulo desde resultados/sugerencias.
3. El sistema agrega un renglon al cuerpo.
4. El operador puede ajustar `cantidad`, `precio`, `bonificacion` e `importe` si corresponde.
5. Cada nueva busqueda suma otro renglon al mismo presupuesto.

### Terminologia para no mezclar conceptos

- `Iterar` en esta etapa significa **seguir agregando renglones al cuerpo**.
- `Nueva version` del presupuesto debe tratarse como concepto separado y posterior.

---

## Origen de datos previsto

### Cliente

Origen base:

- `crm_clientes`

El cliente CRM ya guarda datos comerciales utiles para autocompletar:

- `id_gva01_condicion_venta`
- `id_gva10_lista_precios`
- `id_gva23_vendedor`
- `id_gva24_transporte`
- IDs internos Tango asociados

Esto permite resolver defaults locales sin depender de una consulta remota en cada apertura.

### Catalogos comerciales

Los catalogos de:

- condiciones de venta
- listas de precios
- vendedores
- transportes

deben consumirse desde la integracion comercial activa, hoy alineada con Tango/Connect.

La UI debe mostrar descripcion amigable, pero persistir tanto el codigo como el ID interno si el origen lo entrega.

### Articulos

Origen base del selector:

- `crm_articulos`

Pendiente funcional importante:

- relevar como quedara la fuente comercial real de precios por lista;
- limpiar articulos que deban eliminarse del circuito CRM;
- definir si el precio unitario del renglon sale de cache local propia, de sync de precios por lista o de una consulta comercial puntual.

Mientras ese relevamiento no este cerrado, el modulo no debe atarse a `precio_lista_1` / `precio_lista_2` de Tiendas como contrato definitivo.

---

## Reglas de persistencia

Al guardar un presupuesto, se recomienda persistir:

### Cabecera snapshot

- fecha
- cliente_id
- cliente_nombre
- deposito_codigo / deposito_nombre
- condicion_codigo / condicion_nombre / condicion_id_interno
- transporte_codigo / transporte_nombre / transporte_id_interno
- lista_codigo / lista_nombre / lista_id_interno
- vendedor_codigo / vendedor_nombre / vendedor_id_interno

### Renglones snapshot

- articulo_id
- articulo_codigo
- articulo_descripcion
- cantidad
- precio_unitario
- bonificacion
- importe
- orden

### Totales

- subtotal
- descuento_total
- impuestos_total (si aplica mas adelante)
- total

El snapshot evita que un presupuesto historico cambie si luego se modifica el cliente, el articulo o el catalogo remoto.

---

## Modelo propuesto minimo

### `crm_presupuestos`

Tabla cabecera operativa:

- `id`
- `empresa_id`
- `numero`
- `fecha`
- `cliente_id`
- `cliente_nombre_snapshot`
- `deposito_codigo`
- `deposito_nombre`
- `condicion_codigo`
- `condicion_nombre`
- `condicion_id_interno`
- `transporte_codigo`
- `transporte_nombre`
- `transporte_id_interno`
- `lista_codigo`
- `lista_nombre`
- `lista_id_interno`
- `vendedor_codigo`
- `vendedor_nombre`
- `vendedor_id_interno`
- `subtotal`
- `descuento_total`
- `impuestos_total`
- `total`
- `estado`
- `created_at`
- `updated_at`

### `crm_presupuesto_items`

- `id`
- `presupuesto_id`
- `empresa_id`
- `orden`
- `articulo_id`
- `articulo_codigo`
- `articulo_descripcion`
- `cantidad`
- `precio_unitario`
- `bonificacion`
- `importe`
- `created_at`
- `updated_at`

Nota:

- para la primera vuelta no hace falta abrir aun `crm_presupuesto_iteraciones` si el modulo base todavia no esta estabilizado;
- si luego se confirma `Nueva Version`, el modelo debera migrar a `cabecera` + `iteraciones` + `items por iteracion`.

---

## Comportamiento UX esperado

### Alta / Edicion

- formulario sabana alineado con CRM;
- cabecera arriba;
- buscador de articulo debajo de la cabecera;
- tabla de renglones en el centro;
- totales al pie;
- sin concepto de `carrito`.

### Cliente

- selector con sugerencias locales sobre `crm_clientes`;
- al confirmar cliente, cargar defaults comerciales asociados;
- permitir override manual si el operador necesita ajustar.

### Articulo

- buscador por `codigo` o `descripcion`;
- sugerencias cortas;
- al seleccionar, agregar renglon al cuerpo;
- evitar autofiltrado agresivo del listado principal.

---

## Riesgos detectados

1. **Precios por lista no cerrados**
   - hoy no existe todavia un contrato estable de precios CRM por lista como el que el presupuesto necesita.

2. **Dependencia remota excesiva**
   - si al elegir cliente se consulta remoto cada vez, el formulario se volvera fragil cuando haya mas conexiones o latencia.

3. **Mezcla involuntaria con Tiendas**
   - usar listas o precios del circuito Store romperia el objetivo del entorno CRM.

4. **Versionado prematuro**
   - meter `Nueva Version` antes de estabilizar cabecera + items puede duplicar complejidad demasiado temprano.

---

## Decision recomendada para la siguiente iteracion

Implementar `Presupuestos CRM` en este orden:

1. tabla cabecera `crm_presupuestos`;
2. tabla de renglones `crm_presupuesto_items`;
3. alta/edicion/listado base;
4. autocompletado comercial al elegir cliente;
5. busqueda y acumulacion de articulos en renglones;
6. definicion final del origen de precios por lista;
7. recien despues, impresion y configuracion documental.

---

## Nota sobre futuro documental

El configurador tipo Crystal / A4 / canvas sigue siendo objetivo valido, pero debe apoyarse en un modulo ya resuelto.

La base transversal de esa mecanica queda estandarizada en:

- `docs/print_forms_canvas_standard.md`
- `docs/print_forms_canvas_tecnico.md`

Primero se define y estabiliza:

- la estructura del presupuesto;
- la persistencia;
- el autocompletado comercial;
- el cuerpo de renglones;
- el calculo de totales.

Despues se construye la capa documental sobre esa base.
