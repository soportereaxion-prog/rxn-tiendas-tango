# Log de Modificación - Importador de Notas Tango

**Fecha**: 2026-04-02
**Hora**: 15:40

## Qué se hizo
Se implementó de manera exitosa el script `tools/import_tango_notas.php` que lee notas alojadas en la tabla CRM_NOTAS (base SQL Server Tango), procesa y decodifica su contenido, y las inserta en la base de datos local `rxn_tiendas_core` utilizando el repositorio propio del módulo `CrmNotas`.

## Por qué
El módulo de Notas de CRM necesitaba inicializarse en su ambiente nativo (`rxn_suite`) poblado con la data heredada de la otra instancia SQL Server para poder seguir el hilo de seguimiento de los clientes. El formato antiguo (Tango ERP) estaba guardado en RTF codificado en Hexadecimal `VARBINARY(MAX)` y resultaba en cadenas ininteligibles dentro de MySQL si se migraba directamente.

## Impacto
- El componente `CrmNotas` ahora cuenta con ~860 notas legibles importadas en texto plano estandarizado.
- Ninguna base de datos ni entorno productivo se corrompió debido a la extracción aislada y el testeo en Sandbox (`zzz_SDM_1`).
- Los clientes correspondientes (`crm_clientes` via `COD_Tango`) han quedado vinculados correctamente con sus respectivas notas.

## Decisiones Tomadas
1. **Limpieza del RTF en PHP**: En vez de usar `CONVERT()` y depender de librerías dudosas dentro de T-SQL, o depender de la función nativa lenta, optamos por construir un algoritmo analizador en PHP (Mini Parser RTF) que recorre las capas, descarta configuraciones estéticas de estilo/tablas, decodifica hex (UTF-16LE / ISO-8859-1) recursivamente y escupe una cadena prístina con retornos de carro puros.
2. **Ejecución Bootstrap nativa limpia**: Se separó la dependencia `public/index.php` para que el CLI tool no interactúe con el entorno web y prevenga que la app termine el thread tempranamente con `exit()`. En cambio ahora, utilizamos la inyección manual utilizando el autoloader de Composer, lo que expone instancias puras de bases de datos `Database::getConnection()`.
3. **Idempotencia**: Se inyectó una función de detección manual (`SUBSTRING(contenido, 1, 50)`) para asegurar que si el script es re-ejecutado múltiples ocasiones, las notas no serán nunca duplicadas.
