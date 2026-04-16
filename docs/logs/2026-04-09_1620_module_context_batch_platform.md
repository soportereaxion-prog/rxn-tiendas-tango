# Control de Cambio Documental: MODULE_CONTEXT Batch (Plataforma e Integraciones)
**Fecha:** 2026-04-09 16:20

## Resumen de la Intervención
Se completó la documentación del bloque de plataforma e integración (`PrintForms`, `RxnLive`, `Tango`) y se revisaron los contextos ya existentes de `Auth`, `Articulos` y `RxnSync`. La tanda fue tomada por `clau-direct` como fallback luego de una falla externa de permisos en `gemi-direct`; Lumi auditó el resultado y ajustó los puntos desalineados con el código real.

## Módulos Cubiertos
1. **PrintForms:** editor canvas, versionado y render de formularios imprimibles por empresa.
2. **RxnLive:** dashboards analíticos en tiempo real y persistencia de vistas por usuario/empresa.
3. **Tango:** capa de integración con Tango Connect, sync masivo y utilidades transaccionales.
4. **Auth:** auditado y ajustado.
5. **Articulos:** auditado y mantenido sin cambios adicionales.
6. **RxnSync:** auditado y ajustado.

## Ajustes de Auditoría Reales
- **Auth:** se dejó asentado que `PasswordResetController` y `VerificationController` operan sobre `usuarios` y `clientes_web`, no sólo sobre `usuarios`; además se documentó el set completo de variables relevantes de sesión y la excepción de verificación por GET tokenizado.
- **RxnSync:** se reforzó la sección explícita de seguridad y se mantuvo documentada la diferencia entre el lookup individual paginado y las auditorías masivas que siguen limitadas a primera página remota.
- **Articulos:** el contexto existente ya estaba suficientemente detallado y no requirió corrección adicional en esta tanda.

## Controles de Seguridad Revisados y Documentados
- **Aislamiento Multiempresa:** documentado en todos los módulos del batch mediante `Context::getEmpresaId()` o contexto equivalente.
- **Permisos y Guards:** se dejó asentado cuándo el módulo sólo exige `requireLogin()` y cuándo no existe guard granular adicional por rol.
- **No mutación por GET:** documentadas las excepciones reales detectadas (`Auth` verifica cuenta por GET tokenizado; `RxnSync` expone `getPayload()` por GET como sólo lectura).
- **Validación server-side y escape/XSS:** explicitados los puntos sensibles del renderer de `PrintForms`, los payloads hacia Tango y la hidratación de sesión de `Auth`.
- **CSRF:** documentado como deuda activa en módulos donde no se observó validación explícita en endpoints mutadores.

## Hallazgos Relevantes
- `PrintForms` guarda assets bajo `public/uploads/print-forms/...`, por lo que la accesibilidad por URL quedó señalada como superficie sensible.
- `Tango` y `RxnSync` concentran operaciones de alta criticidad sobre integraciones y catálogos; cualquier cambio en payloads o paginación impacta varios módulos aguas abajo.
- `Auth` mezcla backoffice y B2C en los flujos de verificación/reset; ese acoplamiento quedó ahora documentado para evitar fixes parciales que rompan una de las dos mitades.

## Estado
Documentación creada, auditada y alineada con el código real sin tocar lógica ejecutiva.
