# MODULE_CONTEXT — CrmPresupuestos

## Nivel de criticidad
MUY ALTO. Es un módulo transaccional Core del CRM enfocado en la cotización comercial. Emite propuestas, asocia catálogos comerciales con clientes y envía documentos formalizados a Tango Connect (Facturación/Ventas).

## Propósito
Sustentar el motor de cotizaciones del CRM, permitiendo armar presupuestos, seleccionar artículos desde un catálogo comercial sincronizado, aplicar bonificaciones, generar pre-impresiones PDF y enviarlos al ERP Tango para convertirlos en facturas o pedidos de venta en firme.

## Alcance
**QUÉ HACE:**
- Gestiona el ABM de Presupuestos (con Maestro-Detalle para los renglones/artículos).
- Sincroniza un catálogo local comercial de artículos desde Tango (`CommercialCatalogSyncService`, `CommercialCatalogRepository`).
- Envía presupuestos formalizados al ERP Tango (`PresupuestoTangoService`).
- Renderiza contexto de impresión con plantillas (`CrmPresupuestoPrintContextBuilder`).
- Búsqueda visual en grilla, paginación, filtros de estado.

**QUÉ NO HACE:**
- No permite cobrar ni aplicar medios de pago. Es exclusivamente preventa / cotización.
- No administra clientes directamente; consume el catálogo proveído por `CrmClientes`.

## Piezas principales
- **Controladores:** `PresupuestoController`.
- **Servicios:** `PresupuestoTangoService`, `CommercialCatalogSyncService`, `CrmPresupuestoPrintContextBuilder`.
- **Repositorios:** `PresupuestoRepository`, `CommercialCatalogRepository`.
- **Vistas:** `views/index.php`, `views/form.php`.
- **Rutas/Pantallas:** `/mi-empresa/crm/presupuestos`.
- **Tablas/Persistencia:** `crm_presupuestos`, `crm_presupuestos_renglones`, `crm_catalogo_comercial` (caché de artículos).

## Seguridad Base (Política de Implementación)
- **Aislamiento Multiempresa**: OBLIGATORIO Y ESTRICTO. La consulta de cabecera de presupuesto, renglones y catálogos usan `empresa_id` inyectado forzosamente desde `Context::getEmpresaId()`.
- **Permisos / Guards**: Validados por `AuthService::requireLogin()`. Los usuarios sólo pueden operar sobre cotizaciones de su organización.
- **Mutación**: Todo evento de creación, actualización y borrado suave ocurre bajo verbos transaccionales apropiados (POST).
- **Validación Server-Side**: Los datos del form que representan el maestro-detalle (cabecera + N renglones de artículos con cantidades, precios y bonificaciones) deben ser validados estructuradamente antes de guardar o enviar a Tango para no emitir comprobantes inválidos.
- **Escape Seguro (XSS)**: Textos de observaciones, comentarios para el cliente y descripciones custom en renglones deben ir escapados al renderizar la vista y el builder del PDF.
- **Acceso Local**: Sujeto al token de la empresa en la sesión.

## Dependencias directas
- `App\Modules\CrmClientes\CrmClienteRepository` para cargar el receptor de la cotización.
- Lógica transaccional de Tango vía `App\Modules\Tango\TangoService`.

## Dependencias indirectas / impacto lateral
- El funcionamiento del módulo depende enormemente de que la sincronización del `CommercialCatalogSyncService` esté sana. Si Tango modifica las clases o alias de sus artículos, y no bajan al CRM, no se podrán armar presupuestos válidos.

## Reglas operativas del módulo
- La estructura de guardado suele ser atómica. Guardar la cabecera e iterar y salvar los renglones (artículos).
- Un presupuesto enviado a Tango con éxito usualmente bloquea sus ediciones locales de cantidades/precios para no divergir de lo declarado en AFIP/Ventas (lógica de estado).

## Tipo de cambios permitidos
- Agregar columnas de cálculo, subtotales o lógica de impuestos (IVA, IIBB percibidos) visualmente en el DOM y en los resúmenes PDF.
- Optimizar la carga asíncrona de artículos en el grid modal del formulario de cotización.

## Tipo de cambios sensibles
- Tocar el normalizador numérico (Cálculo de alícuotas, redondeos a 2 decimales y sumatorias totales). Si hay un desvío de 1 centavo entre el cálculo del CRM y lo que recibe Tango, el conector suele rechazar el lote transaccional.
- Modificar el flujo de Sync de Catálogo Comercial.

## Riesgos conocidos
- **Inconsistencia de Precios:** Como el catálogo comercial se actualiza periódicamente de forma asíncrona ("Caché local"), existe la posibilidad operativa de que un presupuesto se emita con una Lista de Precios de las 10 AM, y se envíe a Tango a las 11 AM luego de que el ERP haya subido precios internamente, generando un mismatch o rechazo.

## Checklist post-cambio
- [ ] Listado de presupuestos se muestra respetando tenant (empresa).
- [ ] Edición y Guardado de renglones recalcula correctamente sumatorias y no deja renglones huérfanos.
- [ ] Generación PDF de impresión es exitosa y no omite el logo / cabecera de la empresa.
- [ ] Envío a Tango reporta status OK / ERROR correctamente interceptado.
