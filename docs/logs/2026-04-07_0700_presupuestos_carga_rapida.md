# 2026-04-07_0700_presupuestos_carga_rapida

## Qué se hizo
1. Refactorización visual del buscador de artículos en el ABM de Presupuestos (`form.php`) para implementar una "barra de carga ágil" (inline toolbar).
2. Refactorización del comportamiento del autocomplete de Rxn (`crm-presupuestos-form.js`) de manera que, en lugar de agregar la fila silenciosamente por debajo al elegir un artículo sugerido, popule la barra inteligente arriba, de modo que el operador se posicione en `CANT.` o `PRECIO`.
3. Implementación de eventos nativos de teclado para confirmar mediante la tecla `Enter` y de recálculo temporal en el front (subtotal neto temporal).
4. Actualización del área de ayuda del sistema (`operational_help.php`), documentando estos flujos rápidos por teclado e instruyendo claramente el valor de los Selectores de Configuración provistos en Presupuestos mediante la acción `Sync Catálogos`.
5. Modificación del rastreo de versión (`version.php`).

## Por qué
El modo de operación en la creación de Presupuestos (una fila creada automáticamente en vacío tras presionar una sugerencia del autocompletar) penalizaba el tiempo transcurrido por el operador para confeccionar grandes volúmenes de artículos. Los presupuestos ágiles requieren un modelo de grilla inteligente controlable con teclado sin necesitar clics intermedios de mouse en los inputs inferiores.

## Impacto
- Interfaz del módulo `Presupuestos CRM` drásticamente agilizada.
- Incremento notorio en UX: Permite flujo puro `TAB`-`TAB`-`Enter` de Alta Frecuencia.
- Intervenir sobre precios temporalmente ahora es visual e instantáneo (Importe Temp) antes de volcarlo a la tabla inferior oficial. 

## Decisiones tomadas
- **UI Mínima Favorable**: En lugar de recablear todo el dom manipulator interno del Presupuesto (la función de inserción `appendItem`), se intervino y detuvo el proceso de "autollenado" justo al momento de pinchar el _Picker_. El picker cede los datos y la barra inteligente entra en juego como "stage temporal", el cual expone al final el método `appendItem()` clásico. Se mantuvo compatibilidad al 100% con cómo el ABM guardaba originariamente las variables en BD.
- **Sincronización Help**: Debido a que la UI de la plataforma divide la Ayuda en múltiples secciones (`tiendas` y `crm`), y los índices se colapsan por entorno con validadores `$isCrm`, la ayuda de Presupuestos se adosó de forma estricta detrás de "Pedidos de servicio", para reflejar adecuadamente la operatividad del sistema de Ventas/Reparaciones interno a CRM.
