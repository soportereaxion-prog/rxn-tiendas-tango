# Pedidos Web — Contador de Envios y Habilitación Permanente

## Contexto
Por pedido comercial, los administradores de la tienda necesitan auditar visualmente cuántas veces un pedido web intentó ser ingresado a Tango y requieren la disponibilidad permanente del botón de envío, ignorando la traba de estado original que lo ocultaba una vez que el sistema detectaba `enviado_tango`.

## Problema
El sistema contaba con validaciones estrictas en el `PedidoWebController` y en `views/show.php` que bloqueaban la ejecución del bloque de reintento si la bandera pasaba de pendiente a enviado. Acoplado a ello, la tabla transaccional SQL no poseía ninguna columna encargada de contar las transmisiones.

## Decisión
Desplegar un ALTER TABLE hacia `pedidos_web` e implementar una barrera visual de rastreo continuo de la tabla, inyectando un `+1` en las rutinas de confirmación positiva o negativa del Repositorio de pedidos.

## Archivos afectados
- `app/modules/Pedidos/Controllers/PedidoWebController.php` (Eliminada condición de bloqueo)
- `app/modules/Pedidos/views/show.php` (Alterada regla condicional UI e insertado span visual HTML)
- `app/modules/Pedidos/PedidoWebRepository.php` (Rutina de inyección SQL matemática en updates positivos y fallidos)
- Base de datos local (Agregada columna INT default 0)

## Implementación
La vista de `show.php` ahora inyecta en su cabecera un pequeño texto referencial: "Intentos de envío a Tango: N". El botón verde de "Enviar a Tango" persiste a lo largo de todas las operaciones manteniendo un código de color sutil para marcar envíos pre-aprobados (outline) o mandatorios (solid).

## Riesgos
Al desbloquear el límite en el front-end, el usuario puede desencadenar inadvertidamente llamadas repetitivas de API (`Process 19845`). Afortunadamente, las políticas genéricas del Tango Connect usualmente advierten inserciones duplicadas evaluando la variable `NOTA_PEDIDO_WEB` configurada por nosotros.

## Notas
Ninguna.
