# [Empresas] - Generacion automatica y unica de slug

## Que se hizo
- Se incorporo generacion automatica de `slug` al guardar y actualizar empresas.
- El slug ahora se forma a partir del campo `Nombre` y se valida contra otras empresas para evitar colisiones.

## Por que
- El circuito de alta necesitaba dejar resuelta la URL publica de tienda sin depender de carga manual posterior.
- El slug no se estaba generando y eso rompia el flujo esperado de tienda publica por empresa.

## Impacto
- Cada empresa nueva obtiene un slug consistente al crearla.
- Si el nombre ya existe o genera colision, se agrega sufijo incremental (`-2`, `-3`, etc.).
- Al editar el nombre de una empresa, el slug se recalcula manteniendo unicidad global.

## Decisiones tomadas
- Se tomo `nombre` como fuente de verdad para el slug, segun el criterio visual ya definido en el formulario.
- Se mantuvo una solucion simple dentro de `EmpresaService`, sin agregar dependencias externas.
