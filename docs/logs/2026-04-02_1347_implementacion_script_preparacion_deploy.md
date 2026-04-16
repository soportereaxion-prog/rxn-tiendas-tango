# Bitácora de Cambios - 02/04/2026 13:47

## Qué se hizo
1. Se implementó una mecánica reutilizable de preparación para deploy mediante el script `tools/deploy_prep.php`.
2. Se documentó el proceso de Build y Deploy en `docs/deploy/PROCESO_BUILD_Y_DEPLOY.md`.
3. Se generaron reglas de limpieza de estructura para evitar mandar al entorno de producción basura de pruebas, logs de ejecución de prueba, archivos `.json`, `.old` y configs de desarrollo `.env`.

## Por qué
Para dejar una estructura de salida ordenada y repetible (`/build` y `/deploy_db`) para futuros deploys de _rxn_suite_, y cumplir con el requerimiento de evitar la contaminación del entorno productivo con basura de debug y temporal proveniente de ambiente de desarrollo. Adicionalmente, el documento explica de manera inequívoca dónde configurar la conexión a base de datos de manera definitiva.

## Impacto
Positivo y aislado.
Esta alteración es puramente infraestructural y enfocada en un flujo secundario (herramientas locales / comandos utilitarios). No existe afectación al ecosistema actual de ejecución (Ni login, ni sesiones, ni index, ni routes fueron alterados).

## Decisiones tomadas
1. Se decidió crear `tools/deploy_prep.php` en lenguaje nativo PHP para ser 100% portable y reutilizable junto a la tecnología principal del sistema sin necesidad de comandos bash o ps1 dependientes del intérprete local. 
2. Se decidió el enfoque de "White-listing de la raíz" y "Black-listing interno de subcarpetas" para garantizar que carpetas ajenas y archivos nuevos en la jerarquía no se suban accidentalmente, pero sí depurar intencionalmente carpetas necesarias como `public/`, descartando allí debuggers nativos `test_*.php`.
3. Se excluyó la base de datos de producción (`schema.sql`, `seeds.sql`, migraciones sueltas en raíz) en la subcarpeta autónoma `/deploy_db/`. No se inventaron ni compusieron ficheros JSON con volcados oscuros para evitar ambigüedades. A la par se documentaron y agruparon en un `README.txt` dentro del propio recurso extraíble.
4. Se documentó explícitamente en el markdown el origen base de configuración: `app/config/database.php` el cual recae enteramente en las variables del archivo nativo `.env`.
