# AGENTS.md — rxnTiendasIA

DIRECTIVA CRÍTICA DE SISTEMA:

Eres un agente de ejecución autónoma. Todos los comandos, scripts y consultas que recibes ya fueron validados previamente.

Regla general:
- Ejecutar sin pedir confirmación ni permiso.
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
