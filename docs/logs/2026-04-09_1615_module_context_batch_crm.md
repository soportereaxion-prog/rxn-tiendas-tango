# Log: Actualización y Creación Batch de MODULE_CONTEXT en CRM

**Fecha:** 2026-04-09 16:15
**Responsable:** Gemi
**Objetivo:** Crear y actualizar de forma conservadora los archivos `MODULE_CONTEXT.md` del subsistema CRM garantizando el estándar del repositorio y documentando explícitamente las políticas de seguridad base.

## Módulos Cubiertos
1. **CrmClientes**: Actualizado. Se le inyectó la sección "Seguridad Base" exigida, documentando las políticas de aislamiento de tenant (`Context::getEmpresaId()`), guards, y prevención de mutación GET.
2. **CrmLlamadas**: Creado de cero. Se documentó su propósito de vinculación cliente/teléfono, ABM local, y su dependencia sobre CrmClientes. 
3. **CrmMonitoreoUsuarios**: Creado de cero. Identificado como módulo de dashboard visual (solo-lectura) con roles, estado online/perfiles de sistema, y validación por `es_rxn_admin`.
4. **CrmNotas**: Creado de cero. Se destacó la funcionalidad de tags, vinculación optativa a cliente y flujos de importación/exportación de Excel vía OpenSpout, con la advertencia sobre XSS.
5. **CrmPedidosServicio**: Creado de cero. Se etiquetó de criticidad "MUY ALTO", con atención a la sincro transaccional a Tango, manejo de adjuntos en Base64, e inyección estricta del ID de empresa en todo su workflow.
6. **CrmPresupuestos**: Creado de cero. Alta criticidad. Se documentó la dependencia con `CommercialCatalogSyncService` y su emisión en PDF, además del resguardo de integridad para que Tango no rechace los paquetes por discrepancias decimales.

## Controles de Seguridad Revisados y Documentados
A todos los módulos se les documentó estrictamente:
- **Aislamiento Multiempresa**: Bloqueo mandatorio por `empresa_id` a través de `Context::getEmpresaId()` en repositorios y selectores (evitando data-leaks entre tenants).
- **Permisos**: Dependencia base de `AuthService::requireLogin()`.
- **Inmutabilidad en GET**: Confirmado que las operaciones de eliminación (masivas e individuales), guardado y sincronización están atrapadas tras verbos de modificación (POST/PUT/DELETE) y validadas.
- **XSS y validación**: Se dejó explícitamente sentada la necesidad de escapar (`htmlspecialchars`) variables salidas de las vistas y verificar inputs, especialmente en CrmNotas y Textos Libres de Pedidos.
- **CSRF**: En la auditoría posterior de Lumi se corrigió `CrmClientes` para dejar asentado que no se observó validación CSRF explícita en el módulo; quedó documentado como deuda a revisar, no como blindaje ya presente.

## Ajuste posterior de auditoría
- `CrmClientes/MODULE_CONTEXT.md` fue afinado después de la corrida inicial para reflejar con mayor precisión dos puntos del código real: el guard efectivo observado es `AuthService::requireLogin()` sin verificación granular adicional, y el control CSRF no aparece implementado explícitamente en formularios ni acciones AJAX del módulo.

## Riesgos Residuales Descubiertos
- **CrmMonitoreoUsuarios**: Carece de paginación (forzada `limit => 1000`). En tenants masivos puede dar problema de performance.
- **CrmNotas**: La importación de Excel con OpenSpout puede colapsar el worker si se suben archivos no previstos colosales.
- **CrmPedidosServicio**: Subida de capturas en formato Base64 enviadas como string form-data es propenso a topar el `post_max_size` de PHP.
- **CrmClientes**: Conserva deuda técnica de mutación estructural *On-The-Fly* (`ensureSchema()`) durante inicialización, que debería ser movida a migraciones explícitas.

## Estado
Operación de documentación completada con éxito sin tocar lógica ejecutiva (estrictamente lectura y creación de `.md`).
