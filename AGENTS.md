# AGENTS.md — rxn_suite

DIRECTIVA CRÍTICA DE SISTEMA:

Eres un agente de ejecución autónoma.

Regla general:
- No bloquear el flujo con preguntas innecesarias.

EXCEPCIÓN OBLIGATORIA:
Si el usuario solicita explícitamente:
- un informe
- un resumen
- una validación
- una confirmación

DEBES:
- NO ejecutar cambios automáticamente
- RESPONDER primero con el informe solicitado
- ESPERAR instrucciones posteriores antes de ejecutar

Interpretación:
- "hacé", "ejecutá", "aplicá" → ejecutar directo
- "mostrame", "confirmame", "qué pasaría", "auditá" → NO ejecutar, solo responder

Modo implícito:

Si el mensaje contiene verbos en infinitivo técnico sin contexto (ej: "analizar", "evaluar", "revisar"):
→ asumir modo INFORME

Si contiene instrucciones operativas concretas:
→ asumir modo EJECUCIÓN

REGLAS DE DISEÑO DE INTERFAZ Y MÓDULOS:
- Al crear nuevos módulos o dashboards, NUNCA incorporar "leyendas" ni bloques de texto explicativos fijos en la cabecera del módulo, a menos que el usuario lo solicite o comente explícitamente. Los paneles deben mantenerse visualmente limpios.
- Todo dashboard de tarjetas debe incluir el buscador unificado `dashboard_search.php` que responde a los shortcuts `F3` y `/`.

REGLA OBLIGATORIA DE SEGURIDAD PARA IMPLEMENTACIÓN:
Antes de implementar cualquier módulo nuevo o ampliar uno existente, DEBES aplicar la Política de Seguridad Base (guardada en Engram).
Es OBLIGATORIO validar y documentar explícitamente en el `docs/logs`:
- Aislamiento multiempresa (Context::getEmpresaId()).
- Permisos strictos en backend.
- Separación clara entre RXN admin (sistema) vs admin tenant.
- No mutación de estado o borrados por peticiones GET.
- Validación fuerte server-side.
- Escape seguro en salida (preventivo ante XSS).
- Impacto sobre acceso local del sistema.
- Necesidad o no de token CSRF.
Toda implementación debe entregarse junto a un MD de control de cambio indicando qué medidas de seguridad fueron contempladas en el código aportado.

REGLA OBLIGATORIA DE CAMBIOS EN BASE DE DATOS (MIGRACIONES):
A partir de la implementación del motor de Backups y Mantenimiento, el manejo de Base de Datos persigue políticas estrictas:
- **Versionado Exclusivo:** Todo cambio de persistencia (schema, data, fix o seed técnico) debe materializarse SOLO como una migración versionada ejecutable por el módulo de mantenimiento.
- **Inmutabilidad:** Jamás deben modificarse archivos de migración que ya fueron liberados o aplicados. Si un cambio anterior fue defectuoso, se crea una nueva migración correctiva (rollback forward).
- **No Manualidad:** Están prohibidos los cambios manuales ad-hoc en producción. Bajo ninguna circunstancia debe pisarse la base de datos de producción con un backup/dump proveniente de desarrollo.
- **Idempotencia y Calidad:** Para producción ya inicializada (Post-Baseline), los cambios deben ser incrementales, pequeños y estrictamente idempotentes (`IF NOT EXISTS`, `INSERT IGNORE`, comprobaciones de columnas persistidas). Las migraciones no deben mezclar responsabilidades: separar DDL de DML cuando sea lógico, y envolver manipulaciones de datos en transacciones.
- **Workflow Esperado:** Si requiere persistencia → Analizar necesidad de migración → Crear archivo en local de forma puntual → Realizar build/deploy de los *.php → Ejecutar manualmente desde la Interfaz de Mantenimiento (`/admin/mantenimiento`).
