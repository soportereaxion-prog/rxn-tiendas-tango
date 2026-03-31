# Fase 3 — Cache local del perfil de pedido

## Que se hizo
- Se agregaron columnas en `empresa_config` y `empresa_config_crm` para guardar los IDs críticos del perfil (talonario, lista, depósito, vendedor, transporte, condición, flag de moneda) más un snapshot JSON completo y la metadata de sincronización.
- El guardado de Configuración ahora consulta el detalle del perfil en Tango y persiste ese snapshot en ambas áreas; si no hay perfil o empresa seleccionada, limpia los campos cacheados.
- El resolver de pedidos (`TangoOrderHeaderResolver`) usa el snapshot local antes de intentar una consulta remota, por lo que los PDS/pedidos web ya no dependen de request extra cada vez.
- La pantalla de Configuración muestra cuándo se sincronizó por última vez el perfil para que soporte/ops puedan detectar estados viejos sin revisar la base.

## Por qué
- El usuario necesitaba que “el dato se grabe y se traiga del perfil cuando se guarda Configuración”, evitando el ida y vuelta constante contra Connect y asegurando trazabilidad.
- También desbloquea mejoras posteriores (canvas, validaciones, overrides) porque el detalle ya está disponible sin depender del API en tiempo de envío.

## Impacto
- Nuevas instalaciones crean las columnas automáticamente y los repositorios legacy las autoañaden si faltan.
- Guardar Configuración CRM/Tiendas exige que el perfil responda; si Tango no devuelve datos, el guardado se frena con mensaje claro.
- Pedidos Web y PDS aprovechan el snapshot local; solo si está vacío o desalineado vuelven a consultar y registran la contingencia en logs.

## Deuda / pendiente
- Falta enganchar un comando opcional que recorra todas las empresas y regenere snapshots (por ahora se actualizan al guardar manualmente).
- Seguimos dependiendo de la heurística de moneda hasta que Axoft libere el catálogo oficial.
