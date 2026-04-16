# Modificaciones RXN LIVE - Hotfix de Selector de Vistas

## Fecha y Cambio
- **Fecha:** 2026-04-04 09:55
- **Versión:** 1.1.52
- **Cambio:** Se aplicó un hotfix en la estructura HTML del `savedViewsDropdown`.

## Problema Detectado
- En la etapa anterior se actualizó la lógica Javascript para identificar las Vistas por su `id` numérico y leer su estado interno desde un atributo `data-config`. Sin embargo, el código HTML del combo `select` no había sido actualizado correctamente, por lo que mantenía el esquema antiguo donde guardaba el JSON incrustado directamente en el campo `value`.
- **Efecto visual:** Al guardar una vista y recargar (o al elegir una existente), el Javascript buscaba el atributo inexistente, lo cual provocaba un `JSON.parse(null)` y el posterior bloqueo por excepción. Al bloquearse en el `try-catch` gigante, las rutinas de renderizado gráfico de tablas y charts nunca se disparaban, dejando la interface vacía ("Sin datos tabulables").

## Qué se hizo
- Se normalizó el `<option>` del inyector en PHP (`dataset.php`), asignándole `value="ID"`, `data-nombre` y `data-config` para alinear con requerimientos del nuevo controlador Javascript.
