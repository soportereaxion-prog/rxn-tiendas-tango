# Normalización de Ordenamiento de Columnas - Módulos Llamadas y Notas

Se unificó el sistema de ordenamiento en los módulos CrmLlamadas y CrmNotas para estar alineados al ecosistema central del CRM (CrmClientes, Articulos, etc).

## Cambios Realizados
1. Modificación de los controladores (CrmLlamadasController, CrmNotasController) para capturar \sort\ (columna a ordenar) y \dir\ (dirección ASC/DESC) directamente de la URL.
2. Inyección del helper \\\ y la función \\\ en los listados \iews/index.php\ de cada módulo.
3. Actualización de las cabeceras \<thead>\ para implementar enlaces activos con indicadores direccionales ascendentes/descendentes automáticos (▲/▼).
4. Eliminación del selector de \<select>\ manual 'Ordenar Por' en pos de interacciones más limpias.

El sistema se encuenta integrado para un empaquetado general con las mismas directivas que el sistema operativo principal.
