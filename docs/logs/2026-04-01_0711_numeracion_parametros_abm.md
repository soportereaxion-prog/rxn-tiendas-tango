# Log: 2026-04-01 - Numeracion ABM Parametrizable y Fix Presupuesto

## Qué se hizo
1. Se extendió la tabla `empresa_config_crm` y el modelo `EmpresaConfig` con los nuevos campos paramétricos: `pds_numero_base` y `presupuesto_numero_base`.
2. Se arregló un bug silente en `EmpresaConfigService` donde ciertos campos del `$_POST` (incluyendo validaciones anteriores como `tango_pds_talonario_id`) no eran asignados al modelo antes de persistir.
3. Se integraron visualmente los parámetros en la UI del módulo Empresa Config (sección `CRM`) a través de una nueva tarjeta "Numeración y Talonarios Internos".
4. Se modificaron los submódulos PDS y Presupuesto (`previewNextNumero()`) para adoptar matemáticamente el mayor valor entre: el número paramétrico + 1 y el ID secuencial actual de la DB + 1. Esto permite la modificación inicial del correlativo y evita, lógicamente, pisar duplicados.
5. Se corrigió un missing en la UI del ABM `CrmPresupuestos\views\form.php` inyectando el componente visual y la alineación CSS necesarios para que "Número interno" figure de forma unificada (igual a PDS) dentro de la Cabecera comercial, ajustando las proporciones del layout original.

## Por qué se hizo
Para estandarizar el workflow donde una empresa en su módulo de configuración puede reconfigurar las bases de numeración de PDS y Presupuestos según cómo deseen que comience a impactar en la facturación o la lectura visual.

## Impacto
Impacta positivamente tanto en el Frontend del formulario General (que antes adolecía del componente presupuestario) como en los Repositories de creación lógica, incrementando flexibilidad sin requerir reestructuración pesada.

## Decisiones tomadas
- La inyección matemática `max(1, base+1, dbmax+1)` en las consultas es la decisión más liviana a nivel arquitectónico y evita tener que generar triggers o validaciones complejas de duplicación en DB mientras se respeta la política de multi-empresa.
- Se adaptó el layout de 12 columnas del ABM de `Presupuesto` achicando el selector de "Cliente" de tamaño 6 a 4 para hacer lugar equitativo al input local del número.