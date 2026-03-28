# Estandares de Modulos

## Busquedas CRUD

### Regla general
- Los listados CRUD no deben autofiltrarse mientras el operador escribe.
- El filtrado del listado solo debe ejecutarse cuando el operador confirma la accion: `Enter` o boton `Buscar` / `Aplicar`.

### Sugerencias en vivo
- Mientras el operador escribe, el sistema puede mostrar un dropdown liviano con hasta `2` o `3` coincidencias parciales.
- Las sugerencias sirven para asistir la escritura y permitir seleccionar un resultado probable sin recargar el CRUD.
- Al seleccionar una sugerencia, el texto elegido queda cargado en el input, pero el listado todavia no se filtra hasta la confirmacion explicita.
- El valor "confirmado" que filtra el CRUD debe mantenerse separado del valor "en edicion" del input, para que el operador pueda seguir escribiendo nuevas busquedas sin disparar ni bloquear el listado actual.

### Criterio UX
- El input conserva el termino editable.
- El dropdown no reemplaza al buscador principal ni modifica el dataset ya renderizado.
- Si el termino tiene menos de `2` caracteres, no se consultan sugerencias.
- El dropdown debe poder cerrarse con `Escape`, click externo o al confirmar la busqueda.

### Criterio tecnico
- Preferir endpoint liviano de sugerencias con limite corto y whitelist de campos.
- Mantener el listado principal server-rendered y desacoplado del autosuggest.
- Evitar autorefrescos del CRUD por `keyup`, `input` con autosubmit o filtrado agresivo del DOM cuando el patron vaya a escalar.

### Objetivo
- Mejorar velocidad de seleccion para el operador sin castigar rendimiento ni romper previsibilidad del CRUD.

### Documento operativo
- Referencia ampliada: `docs/crud_search_standard.md`
