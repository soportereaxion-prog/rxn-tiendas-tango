# 2026-03-30 — Canvas de impresión para Pedidos de Servicio

## Qué se hizo

Se integró la mecánica de impresión canvas (ya operativa en Presupuestos CRM) al módulo de Pedidos de Servicio (PDS).

## Archivos creados

- `app/modules/CrmPedidosServicio/PedidoServicioPrintContextBuilder.php` — Context builder nuevo. Mapea los datos del PDS en cinco grupos de variables:
  - `empresa` → nombre y CUIT desde `EmpresaRepository` / `EmpresaConfigRepository`
  - `cliente` → nombre, documento, email
  - `articulo` → código, nombre, precio unitario formateado
  - `pedido` → número padded, solicitó, fechas, clasificación, nro_tango, diagnóstico, falla, estado
  - `tiempos` → duracion_bruta, descuento, duracion_neta (HH:MM:SS), decimal (horas)

## Archivos modificados

- `app/modules/CrmPedidosServicio/PedidoServicioController.php`
  - Agregado método `printPreview(string $id)` siguiendo el patrón de `PresupuestoController`
  - Usa `PedidoServicioPrintContextBuilder` + `PrintFormRepository` + `PrintFormRenderer`
  - Si no existe template guarda flash y redirige; si falla el render ídem

- `app/modules/PrintForms/PrintFormRegistry.php`
  - Registrado el documento `crm_pds` con `area: crm`
  - `default_objects` con 35 objetos predeterminados: encabezado, empresa, cliente, identificación técnica, bloque de tiempos (bruto/descuento/neto/decimal), falla, diagnóstico y footer con estado + nro Tango
  - `variables` completas agrupadas (5 grupos, 18 variables)
  - `sample_context` con datos demo realistas para previsualizar en el editor

- `app/modules/CrmPedidosServicio/views/form.php`
  - Botón **Imprimir** → `GET /mi-empresa/crm/pedidos-servicio/{id}/imprimir` (target blank)
  - Botón **Formulario** → `GET /mi-empresa/crm/formularios-impresion/crm_pds`
  - Ambos visibles solo en modo `edit`

- `app/config/routes.php`
  - Nueva ruta: `GET /mi-empresa/crm/pedidos-servicio/{id}/imprimir` → `PedidoServicioController::printPreview`

## Por qué

El PDS es el módulo de trabajo técnico central del CRM. Necesita impresión para documentar y entregar el resultado del servicio (remito técnico, acta de diagnóstico, recibo de trabajo).

## Impacto

- Sin breaking changes en módulos existentes
- El editor `.../formularios-impresion/crm_pds` queda disponible automáticamente al registrar la key en el Registry
- `PedidoServicioPrintContextBuilder` es completamente independiente del de Presupuestos
- La estructura de `default_objects` cubre el flujo completo del PDS sin requerir personalización para ser útil desde el primer día

## Decisiones tomadas

- Se exponen los tiempos en `HH:MM:SS` y también en horas decimales, dado que ambas representaciones son necesarias: una para el documento legible, otra para el registro técnico / Tango
- `descuento_segundos` se formatea como duración aunque en DB sea nulo; el builder devuelve `00:00:00` si falta para no romper el canvas
- El builder reutiliza `PedidoServicioTangoService::decimalHoursFromSeconds` para no duplicar lógica
