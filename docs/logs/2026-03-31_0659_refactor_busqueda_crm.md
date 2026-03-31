# Refactoreo Profundo de Motores de Búsqueda CRM y PDS

**Fecha:** 2026-03-31 06:59
**Impacto:** UI, CRM Clientes, Articulos, CRM Pedidos de Servicio

## Qué se hizo
- Se reestructuraron las consultas SQL subyacentes de los Select2 para priorizar coincidencias visualmente (usando de forma innovadora `ORDER BY CASE WHEN`).
- Se cambiaron los repositiorios impactados: `CrmClienteRepository` (módulo clientes directos), `ArticuloRepository` (módulo Articulos directos) y `ClienteWebRepository` (que el modal PDS utiliza indirectamente).
- Se implementaron nomenclaturas de variables PDO estrictamente individuales (`:o_exact1`, etc.) en vez de nombramientos compartidos (`:search_exact`) para evadir el crash silencioso 500 (`SQLSTATE[HY093]: Invalid parameter number`), limitante real en servidores sin preparaciones emuladas.
- En `ClienteWebRepository->applySearch()`, se incluyó la columna `razon_social` que estaba ausente, reparando el nulo de coincidencias que experimentaban los formularios tipo PDS cuando un cliente corporativo de Tango nunca se había cargado localmente con los atributos 'Nombre' y 'Apellido'.

## Por qué
- La experiencia operativa demostraba frustración extrema para los ejecutivos comerciales al escribir "VILLEG" y obtener primeros resultados de la base con patrones basura en sus códigos Tango, y recién más abajo (a veces extirpados del vector visual por el limitador de sugerencias) el cliente Villegas S.A.
- UX Comercial Dictamina: Para el cerebro humano, el vector asociativo 1 es Razón Social / Nombre. Código de sistema va secundario.

## Decisiones tomadas
- La jerarquía matemática estricta a nivel SQL se transpone así:
  1. `Razón Social / Nombre Exacto`
  2. `Razón Social / Nombre StartsWith` ...
  3. `Código Tango` y variantes
  4. `Email`

- Validado al 100% que la query escala en miles de registros sin golpear un for-loop oneroso en PHP.
