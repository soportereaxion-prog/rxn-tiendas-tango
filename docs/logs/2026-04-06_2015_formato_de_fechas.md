# Formato Dinámico de Fechas en RXN Live

## Qué se hizo
Se implementó un control en la cabecera de los datasets analíticos de **RXN Live** para permitir la visualización de fechas en el formato preferido por el usuario.

El usuario ahora cuenta con un selector junto al botón "Limpiar Filtros" con opciones:
- YYYY-MM-DD (Base)
- DD/MM/YYYY
- DD-MM-YYYY
- DD/MM/YY
- DD-MM-YY

1. Se añadió la variable global `globalDateFormat`.
2. Se construyó el parser dinámico `formatRxnDate()` en Javascript.
3. Se integró la lógica al renderizador de vista plana (`renderPlana()`).
4. Se agregó la serialización al momento de guardar la configuración volátil o persistente de las vistas `extractViewConfig()`, `applyViewConfig()`.

## Por qué
Para brindar mayor personalización y comodidad a nivel visual cuando los datasets exportan largas listas de datos temporales, especialmente porque la base opera en `YYYY-MM-DD` pero localmente es habitual el uso del formato día-mes-año.

## Impacto
* **Frontend:** Modificado `app/modules/RxnLive/views/dataset.php` con la UI y JS nuevos.
* **Backend:** Ningún impacto en controladores, todo se procesa en el DOM sin perder la naturaleza de los datos base.

## Decisiones tomadas
* Todo el parseo en vez de incorporar la pesada librería moment.js se resolvió con Regex puro (`formatRxnDate()`) para no abultar dependencias sobre una necesidad acotada, respetando la política estricta de eficiencia.
* Las fechas transformadas no alteran el filtro de la columna plana que internamente se sigue basando en los valores originales para la sincronía.
