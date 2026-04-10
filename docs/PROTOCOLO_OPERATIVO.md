## Flujo operativo de agentes

### Roles

- `Lumi`: orquestadora principal. Analiza el pedido, ordena el contexto, decide la via de ejecucion y traduce el resultado al usuario.
- `gemi-direct`: ejecutora principal. Hace la tarea operativa concreta por via directa y sincrona.
- `clau-direct`: ejecutora de respaldo. Entra cuando `gemi-direct` falla, no responde utilmente, entra en loop o hace falta una segunda via.

### Orden de uso

1. Lumi analiza el pedido.
2. Lumi prepara contexto, restricciones y criterio de exito.
3. Lumi ejecuta primero con `gemi-direct`.
4. Si el resultado no sirve o la ejecucion falla, Lumi escala a `clau-direct`.
5. Lumi valida el resultado y recien entonces responde al usuario.

### Regla principal

- No usar `delegate` ni `delegation_read` como mecanismo operativo principal para consultar a Gemi o Clau.
- La via recomendada y estable es la ejecucion directa y sincrona.

### Invocacion recomendada

```bash
opencode run --agent gemi-direct "<pedido>"
opencode run --agent clau-direct "<pedido>"
```

### Criterio de escalamiento

Escalar de `gemi-direct` a `clau-direct` cuando ocurra cualquiera de estos casos:

- error de ejecucion
- salida vacia o no util
- loop, limbo o respuesta inconclusa
- necesidad de una segunda via operativa

## Capturas e imagenes

### Directorio operativo

- Directorio oficial: `docs/contexto/capturas/`

### Convencion de nombres

- Formato: `YYYY-MM-DD_HHMM_descripcion_breve.png`
- Ejemplo: `2026-04-09_2045_error_delegate_gemi.png`

### Criterio de uso

- Guardar ahi capturas de UI, errores visuales, layouts rotos, evidencia de consola o estados operativos que convenga pasar como contexto.
- Si una tarea depende de evidencia visual, Lumi debe incluir la ruta exacta en el pedido que manda a `gemi-direct` o `clau-direct`.

### Texto recomendado para referenciarlas

- `Tomar en cuenta la captura: docs/contexto/capturas/2026-04-09_2045_error_delegate_gemi.png`

### Regla practica

- Si hay una captura relevante, no describirla de memoria cuando se puede pasar la ruta exacta.
- Usar nombres breves, especificos y consistentes para que la evidencia visual se encuentre rapido.
