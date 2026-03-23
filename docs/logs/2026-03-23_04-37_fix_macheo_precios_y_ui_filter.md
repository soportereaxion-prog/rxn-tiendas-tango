# [Artículos] — [Corrección Macheo Precios & UI]

## Contexto
El macheo de Precios entre las listas `20091` e inventario local fallaba intermitentemente por alteraciones destructivas de espaciado en la Primary Key conceptual.

## Problema
Al sincronizar Artículos (`process=87`), el `ArticuloMapper` inyectaba un `trim()` que decapaba los "espacios de padding" típicos que la tabla `STA11` de Tango suele tener. Como consecuencia, el servicio de Precios no encontraba Match al hacer iteraciones contra la base SQL porque el `codigo_externo` guardado estaba truncado en sus extremos, rompiendo la integridad de la sincronización. Los códigos lucían como `C105 090BCO` en UI en contraposición a `01050 BCO090` en DB Tango.

## Decisión
- **Respeto Absoluto SQL**: Se retiró la instrucción `trim` forzada sobre `$codigoExterno` y `$sku` en los mappers de ambas sincronizaciones. A partir de ahora se preservan espacios, ceros y longitudes originarias exactas exigiendo `COD_STA11 === codigo_externo` riguroso.
- **Grilla UI Filtrable**: Dado el volumen que implica tener catálogos reales, implementamos un JavaScript reactivo Vanilla (`filterTable()`) con un `input` iterativo en el Header para cribar la tabla usando `onkeyup`.

## Archivos afectados
- `app/Modules/Tango/Mappers/ArticuloMapper.php`
- `app/Modules/Tango/Services/TangoSyncService.php`
- `app/modules/Articulos/views/index.php`

## Implementación
1. Se borró toda incidencia de `trim` al decodificar `COD_STA11` en `fromConnectJson` de `ArticuloMapper` y `syncPrecios` de `TangoSyncService`. Dicha modificación no altera `DESCRIPCIO` ni `SINONIMO` como manda la directiva superior.
2. Se inyectó un Input de Búsqueda sobre el panel izquierdo superior del grid en el archivo `views/index.php`.
3. Se anexó lógica procedural JS debajo del Footer delegando el render a `display:none` condicionado por matches instantáneos de substring.

## Impacto
Los artículos que se inserten o modifiquen al invocar "Sync Artículos" guardarán de forma vitalicia el esquema real (con eventual basura o padding de strings). Posteriormente, al gatillar "Sync Precios", el algoritmo macheará en una equivalencia estricta en el `UPDATE` SQL nativo.

## Validación
- Revisión cruzada de sentencias lógicas.
- Puesta a disposición para Testing Analítico en Cliente MS SQL contra EmpreB (Comparativas GVA17 y STA11).
