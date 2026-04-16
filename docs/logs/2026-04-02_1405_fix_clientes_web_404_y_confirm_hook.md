# Fix Not Found en Eliminación de Clientes Web y Globalización Modal

## Qué se hizo
1. Se cerró correctamente el formulario `hiddenFormBulk` en `app/modules/ClientesWeb/views/index.php`. En la implementación anterior, este formulario abría antes de la tabla y cerraba después de esta, envolviendo a todos los mini-formularios de eliminación (`<form action=".../eliminar">`), lo cual generaba una estructura de formularios anidados (invalida en HTML5).
2. Se reescribió la lógica central de intercepción en `public/js/rxn-confirm-modal.js`. Se añadió soporte para cazar eventos en componentes definidos genéricamente con la clase CSS `.rxn-confirm-form` y obtener el mensaje de atributos `data-msg` en distintos tipos de delegación.

## Por qué
El usuario experimentaba un error `404 Not Found` al intentar eliminar un cliente desde el botón individual del listado.
El error se producía en forma secuencial debido a dos problemas arquitectónicos interactuantes:
*   **Fallo del Confirm Modal Hook:** Al unificar el UI previamente y reemplazar el atributo específico `data-rxn-confirm` por `data-msg`, el detector JS en `rxn-confirm-modal.js` dejó de atrapar los eventos porque solo vigilaba el primer atributo de datos.
*   **Afloramiento del problema HTML Subyacente:** Al fallar el hook JS, el navegador simplemente ejecutó el submit del formulario envuelto. Sin embargo, dado que HTML ignora la apertura de sub-formularios (`<form>` anidados), el navegador interpretó el evento submit como proveniente del formulario maestro (`hiddenFormBulk`). Dado que este formulario de gran alcance carece del atributo "action", el POST resultante se enrutó incorrectamente a la URI inicial de la vista (`/mi-empresa/clientes`), endpoint que carece de registro en el router para el método POST, resultando en un 404.

## Impacto
*   **Directo:** Se resuelve totalmente la funcionalidad de mover/eliminar individualmente los registros para "Clientes Web". Otorga consistencia directa frente a la API Controller.
*   **Transversal (Global):** El parche ampliado en el modal `rxn-confirm-modal.js` hace que el comportamiento sea compatible y sumamente estricto tanto con `data-msg`, llamadas tradicionales, y botones de confirmación anclados usando `.rxn-confirm-form`. Modulos enteros del CRUD actualizados a *gold standard* ya no requieren del bloque JS en-línea para iterar e invocar prompts sobre "rxn-confirm-form".

## Decisiones tomadas
* Se mantuvo la delegación centralizada en `document.addEventListener('click', ...`) en lugar de amarrar bindings en OnContentLoad para evitar dependencias adicionales que puedan colapsar con reemplazos asincrónicos futuros (ej: HTMX).
* Se dio soporte tanto para botones directos de envío inter-estructuras (`input[type="submit"]`), links estáticos `<a>`, o `submit form="id"`.
