## Propósito
Solucionar el fallo reportado en el que el campo Fecha y hora de inicio se reseteaba inyectando la fecha actual después de un intento de guardado fallido (ej: error de validación en creación).

## Qué se hizo
- Se analizó el comportamiento de la vista de PDS (pp/modules/CrmPedidosServicio/views/form.php).
- Se interceptó el script JS que actuaba como poblador de localISOTime que corre únicamente bajo la condición $formMode === 'create'.
- Se corrigió la condición del script para que solo se ejecute cuando el formulario es fresco: \ === 'create' && empty(\).

## Por qué
El motor PHP en caso de fallo de requerimiento reenviaba vía POST todos los inputs cargados correctamente para no perderlos, y el DOM rellenaba el alue de echa_inicio en el <input>. Sin embargo, centésimas de segundo después, el bloque de JS incondicional sobre-escribía ese valor inyectando 
ew Date(). Al atarlo a la condición de empty(\), logramos que si la vista viene rechazada por un 422 de backend, el script no se emita en el cuerpo y el HTML conserve la fecha tipeada/copiada previamente.