# Auditoría de Contexto: CrmClientes
Fecha: 2026-04-08 18:07

## Qué se hizo
Auditoría estructural del módulo `CrmClientes` y generación del archivo de referencia `MODULE_CONTEXT.md`.

## Por qué
Para establecer un contexto técnico comprobado del módulo CRM Clientes de forma preventiva frente a futuras iteraciones transaccionales (tales como PDS y Presupuestos) evitando asunciones que rompan integraciones dependientes.

## Impacto
- Sin impacto funcional local. Solo revisión estática y registro literario.
- Quedó documentada la respuesta estricta del controller para JSON sugerencias (`['id', 'label', 'value', 'caption']`), el patrón heurístico de búsqueda de keys "difusas" en el payload de Connect, y la naturaleza *On-The-Fly* del modelo de persistencia `ensureSchema()`.
- Se prohíbe alterar el mecanismo de matching sin la validación previa correcta debido al alto impacto sobre las operaciones ya validadas.

## Decisiones tomadas
Solo registrar hallazgos empíricos del código PHP sin intervenir. La auditoría está alineada con el protocolo de calidad para no rediseñar "todo" en pasos prematuros pero evitando deuda técnica oculta.
