# Feature: Canvas Export/Import en el Editor PrintForms

**Fecha:** 2026-04-03 21:56  
**Área:** PrintForms / Editor Canvas  
**Tipo:** Feature

---

## Qué se hizo

Se implementó un mecanismo de exportación e importación de canvas directamente desde el editor visual de PrintForms.

---

## Por qué

El usuario necesitaba una forma de portar diseños de canvas entre entornos (local → producción) sin depender de dumps de base de datos ni migraciones manuales.

---

## Cómo funciona

### Exportar
- Botón **"Exportar"** (verde, `bi-box-arrow-up`) en la toolbar del canvas.
- Descarga un archivo `{document_key}.canvas.json` con:
  - `meta`: formato, key y timestamp.
  - `page_config`: orientación, color de fondo, transparencia, grilla.
  - `objects`: todos los objetos del canvas.
  - `fonts`: fuentes usadas.
  - `background.url`: URL de la imagen de fondo.
  - `background.data`: imagen de fondo embebida en **base64** (intenta fetchearla para portabilidad cross-env).

### Importar
- Botón **"Importar"** (amarillo, `bi-box-arrow-in-down`) en la toolbar del canvas.
- Abre un selector de archivo `.json`.
- Restaura `page_config`, `objects`, `fonts` y panel de controles.
- Si el JSON trae el fondo en base64, lo inyecta en el file input de imagen para que el próximo **"Guardar versión"** lo suba automáticamente como asset.
- Muestra un toast de confirmación con el nombre del canvas y cantidad de objetos importados.
- El estado importado es reversible con **Ctrl+Z** (se guarda en el undo stack antes de importar).

---

## Impacto

- No requiere cambios de base de datos.
- Flujo de trabajo: Exportar en local → Importar en producción → Guardar versión → listo.
- Retrocompatible: si el fondo no se puede embeder (CORS, archivo inexistente), exporta igualmente con solo la URL.

---

## Archivos modificados

- `public/js/print-forms-editor.js` — Métodos `exportCanvas()` e `importCanvas()`, y handler de clicks para los nuevos botones.
- `app/modules/PrintForms/views/editor.php` — Botones "Exportar" e "Importar" en la toolbar del canvas.

---

## Seguridad

- El import valida el formato `rxn-canvas-v1` antes de aplicar.
- Ningún dato llega al servidor sin pasar por el flujo normal de "Guardar versión".
- El file input de importación solo acepta `.json`.
