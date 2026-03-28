# Estandar Operativo - Busquedas CRUD y Autosuggest

## Proposito

Definir un patron unico para los buscadores de listados CRUD del backoffice y futuros modulos como CRM, evitando comportamientos inconsistentes, filtros agresivos o experiencias visuales distintas entre pantallas.

## Regla madre

- El CRUD nunca se autofiltra mientras el operador escribe.
- El operador puede escribir libremente, recibir sugerencias y confirmar la busqueda solo con `Enter` o boton `Buscar` / `Aplicar`.

## Objetivos funcionales

- Acelerar la localizacion de registros frecuentes.
- Mantener predecible el comportamiento del listado.
- Evitar recargas innecesarias cuando el volumen crezca.
- Reutilizar el mismo patron visual y tecnico entre modulos.

## Patron UX obligatorio

### Estructura visual
- Selector `Buscar por`.
- Input principal de busqueda.
- Dropdown de sugerencias parciales.
- Boton `Buscar` o `Aplicar`.
- Accion `Limpiar filtros` cuando existan filtros activos.
- Texto de ayuda corto y consistente.

### Flujo esperado
1. El operador escribe en el input.
2. Si el termino tiene `2` o mas caracteres, se consultan sugerencias.
3. El sistema muestra hasta `3` coincidencias parciales.
4. Si el operador elige una sugerencia, el texto queda cargado en el input.
5. El CRUD sigue intacto hasta que el operador confirma la busqueda.
6. Al confirmar, el listado aplica filtros, conserva orden y reinicia pagina si corresponde.

### Interacciones minimas
- `Enter`: confirma la busqueda.
- click en `Aplicar`: confirma la busqueda.
- `Escape`: cierra sugerencias.
- click externo: cierra sugerencias.
- flechas `arriba/abajo`: navegan sugerencias si estan visibles.

## Regla tecnica clave

Separar siempre:

- `valor editable`: lo que el operador esta escribiendo ahora;
- `valor confirmado`: lo que efectivamente filtra el CRUD.

Sin esta separacion, el input queda "pegado" al ultimo filtro aplicado y la experiencia se rompe en la siguiente busqueda.

## Criterios de datos

### Campos a incluir
Incluir solo campos que un operador reconoce y usa para encontrar registros.

Buenos candidatos:
- `id`
- `codigo`
- `nombre`
- `slug`
- `email`
- `documento`
- `razon_social`
- codigos comerciales o externos visibles

### Campos a evitar por defecto
- JSON internos
- IDs tecnicos remotos no visibles en UI
- textos largos sin valor operativo
- columnas de auditoria (`created_at`, `updated_at`) salvo caso puntual

### Todos los campos
`Todos los campos` debe significar "todos los campos operativos definidos para buscar", no literalmente toda la tabla.

## Criterio por modulo actual

### Empresas
- `id`
- `codigo`
- `nombre`
- `slug`
- `razon_social`
- `cuit`

### Usuarios
- `id`
- `nombre`
- `email`
- empresa visible asociada

### Articulos
- `id`
- `codigo_externo` / SKU
- `nombre`
- descripcion corta

### Clientes
- `id`
- `nombre`
- `apellido`
- `email`
- `documento`
- codigo comercial visible si existe

### Pedidos
- `id`
- codigo de pedido
- nombre de cliente
- email
- estado, si se decide exponerlo como campo de busqueda textual

### CRM futuro
Aplicar exactamente el mismo patron para:
- clientes
- contactos
- oportunidades
- empresas
- actividades
- tickets si entran al circuito

En CRM, los buscadores deben sentirse parte de una misma familia visual y operativa aunque cambien los datos.

## Lineamientos visuales

- Mantener mismo alto de `select`, `input` y botones.
- Repetir mismo espaciado del toolbar entre modulos.
- Reutilizar mismo dropdown de sugerencias.
- No cambiar labels entre modulos sin necesidad.
- Usar el mismo patron responsive: en mobile, input y botones apilados; en desktop, distribucion horizontal.

## Lineamientos tecnicos

- Listado principal: server-rendered.
- Sugerencias: endpoint JSON minimo y acotado.
- Limite de sugerencias: `3`.
- Campos: whitelist cerrada.
- Sin autosubmit por `input`.
- Sin filtrado del DOM para reemplazar al servidor en listados serios.
- Reiniciar pagina al aplicar una nueva busqueda.

## Checklist de implementacion

Antes de implementar un buscador CRUD nuevo:

1. Definir campos operativos buscables.
2. Definir que muestra `Todos los campos`.
3. Crear endpoint minimo de sugerencias con whitelist.
4. Limitar sugerencias a `3` resultados.
5. Separar valor editable de valor confirmado.
6. Confirmar que solo `Enter` o boton filtran el CRUD.
7. Verificar cierre con `Escape` y click externo.
8. Verificar misma experiencia visual en mobile y desktop.
9. Documentar campos elegidos y rationale en `docs/logs`.

## Plantilla rapida

- Referencia de implementacion corta: `docs/crud_search_template.md`

## Anti-patrones prohibidos

- autofiltrar el CRUD por cada tecla;
- disparar queries completas del listado mientras el usuario escribe;
- mezclar sugerencias con filtrado efectivo del grid;
- usar campos tecnicos irrelevantes solo porque existen;
- hacer que cada modulo tenga una UX de busqueda distinta sin motivo real.

## Decision de arquitectura

Este patron queda adoptado como base para modulos CRUD del backoffice y para el futuro CRM del proyecto.
