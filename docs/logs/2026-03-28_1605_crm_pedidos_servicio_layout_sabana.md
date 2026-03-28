# [CRM/PEDIDOS DE SERVICIO] - Ajuste visual a formato sabana

## Que se hizo
- Se refactorizo la vista `app/modules/CrmPedidosServicio/views/form.php` para abandonar el layout con panel lateral pesado y pasar a una composicion tipo sabana mas densa.
- El encabezado operativo ahora distribuye los campos en una grilla ancha orientada a escritorio, aprovechando mejor una pantalla `1080p` sin perder responsividad.
- El resumen de tiempos y snapshot se movio a una franja horizontal de tarjetas compactas dentro del cuerpo principal.

## Por que
- La primera version resolvia la funcionalidad, pero visualmente quedaba demasiado angosta para un modulo operativo que necesita leer muchos datos juntos.
- El objetivo era acercar el modulo al patron visual interno del sistema y no al aspecto de la pantalla legacy.

## Impacto
- En escritorio entran mas campos visibles sin scroll vertical innecesario y con una lectura mas parecida a una sabana operativa.
- En tablet/mobile la vista sigue colapsando a una sola columna mediante media queries locales y la capa responsive global.

## Decisiones tomadas
- Se mantuvieron `Diagnostico` y `Falla` con el tamano amplio de la iteracion anterior por ser los bloques mas valorados del formulario.
- Se uso una grilla local especifica del modulo en vez de tocar la capa global para no contaminar otros formularios ya estabilizados.
