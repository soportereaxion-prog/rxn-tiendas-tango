# Restauración del Módulo PDS (Funcionalidades Avanzadas)
Fecha: 2026-03-31

## Qué se hizo
1. **Restauración de Selectores de Autocompletado (Pickers)**:
   - Se resolvió un error silencioso de serialización JSON (`JSON_INVALID_UTF8_SUBSTITUTE`) en `PedidoServicioController.php` que fallaba al procesar cadenas no UTF-8 provenientes de la base de datos de Tango ERP (común en `razon_social`). Esto causaba que la vista "no buscara".
2. **Motor de Imágenes y Adjuntos**:
   - Se restauró la lógica de portapapeles (`paste`) en el campo `diagnostico` en `crm-pedidos-servicio-form.js`, generando previsualizaciones asíncronas de `#imagenN` en base64 y materializándolas en el servidor al guardar el formulario.
   - Se re-implementó la tabla `crm_pedidos_servicio_adjuntos` en `PedidoServicioRepository::ensureSchema()`.
   - Modificación de `PedidoServicioController->store()` y `update()` para decodificar, almacenar en disco y registrar en base de datos.
3. **Atajos de Teclado y Botonera (UX)**:
   - Se implementaron atajos de alta performance: `F9` para Guardar/Crear de forma local y `F10` para Guardar y Enviar a Tango.
   - Reubicación de la botonera operativa al final del formulario (`rxn-form-actions`) en vez de usar la barra superior.
4. **Checkbox Timestamp "Ahora"**:
   - Integrado checkbox en el campo "Finalizado" en `form.php` que al activarse inserta automáticamente la hora local formateada en ISO 8601 (sin timezone offset) para facilitar el cierre del pedido.

## Por qué
Tras el `git reset` estructural de la aplicación, el módulo de PDS perdió la capa de "alto rendimiento" obligatoria de los operadores. Estas funciones optimizan el tipeo, carga de visuales diagnostificos, atajos y disminuyen la fricción manual, esenciales para el volumen diario de uso de esta herramienta.

## Impacto
- **Operativo**: PDS recupera sus features de UX avanzados y estabilidad de búsqueda AJAX.
- **Base de Datos**: Ningún impacto directo grave, se aseguró de no sobreescribir updates con un bloque `try/catch` de ALTER TABLE, e insertando un `CREATE TABLE IF NOT EXISTS` para la nueva persistencia de las capturas.
- **Performance**: Imágenes guardadas de forma nativa local-first como files, mejor optimizado que embebidas en la BD.

## Decisiones tomadas
- Mantener la decodificación local-first base64 de imágenes, inyectando un thumbnail temporal con un `<input type="hidden">` array para un submit tradicional.
- Implementar `JSON_INVALID_UTF8_SUBSTITUTE` a nivel endpoint controller general de sugerencias como escudo protector frente a strings Legacy provenientes del ERP Tango.
