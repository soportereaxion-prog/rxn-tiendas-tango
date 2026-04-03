# 2026-04-01 - 13:00 - Estandarización global de atajos F3 y "/" para búsqueda

### Qué se hizo
1. Se auditó por qué el atajo `F3` o `/` no funcionaba en la búsqueda global de ciertos módulos (específicamente `CrmNotas`, `Empresas` y el entorno web/`Store`). 
2. Se inyectó el script `<script src="/rxn_suite/public/js/rxn-shortcuts.js"></script>` en 39 vistas (`index.php`, `crear.php`, `editar.php`, `form.php`) de todos los módulos. Anteriormente, los atajos de teclado no funcionaban porque el listener no estaba cargado en estas pantallas.
3. Se normalizó el `<input>` de búsqueda para asegurar que todos los buscadores del sistema tengan el atributo obligatorio `data-search-input` y el `placeholder='🔎 Presioná F3 o "/" para buscar'`.
4. Se corrigió un error de sintaxis nativo que se había insertado al actualizar los tag `value` dentro de sentencias PHP cortas (`<input value="<?="..." ?>">`) en `Store` y `CrmNotas`.

### Por qué
El usuario advirtió que al listado de Empresas y de Notas (entre otros) no le estaban funcionado los atajos F3 y `/` para hacer foco automático en el cuadro de búsqueda. Esto rompía con la consistencia sistémica del ERP/CRM que define este atajo a nivel global.

### Impacto
Ahora hay consistencia absoluta.
- **En listados (Grillas)**: Presionar `F3` o `/` hace focus automático al buscador para filtrar la información sin tocar el mouse.
- **En abms (Crear/Editar)**: Ya al incluir el `rxn-shortcuts.js` general en todas las pantallas, también comienzan a aplicarse otros atajos (Guardar con `F10`, volver con `Esc`, etc.)
- **Cero dependencias extra**. Todo con js vainilla.

### Decisiones tomadas
- Se aplicó de forma automática buscando vistas modulares donde `rxn-shortcuts.js` estaba ausente. 
- Los inputs de búsqueda en `Store` (Público) obtuvieron esta característica pese a no ser backoffice, manteniendo consistencia total de ERP.
