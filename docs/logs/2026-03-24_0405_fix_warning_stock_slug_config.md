# Iteración Corta: Corrección Front Store y Configuración

## Fecha y Tema
2026-03-24 04:05 - Corrección de Warning en detalle de producto y exposición de la URL pública (Slug) en Mi Configuración.

## 1. Objetivo
* Solucionar el warning `Undefined property: App\Modules\Articulos\Articulo::$stock_actual` en la vista pública `show.php`.
* Exponer permanentemente el `slug` como referencia visual dentro del panel "Mi Configuración" para el operador de turno.

## 2. Causa Raíz (Warning de Stock)
La entidad `Articulo` carecía de los atributos `stock_actual`, `precio_lista_1` y `precio_lista_2`. Adicionalmente, el método `ArticuloRepository::findById()` no estaba hidratando las columnas omitidas. Al llamar a la propiedad desde la vista del front, PHP lanzaba un warning de atributo indefinido en objetos tipados.

## 3. Decisiones y Correcciones 

### Sobre el Stock en el Detalle
Se descartó colocar un "parche visual" (`isset`) en la vista del Front. La arquitectura dictaba completar el contrato de la Entidad.
* Se agregó `public ?float $stock_actual = null;` al modelo `Articulo.php` (así como los precios de lista).
* Se amplió el mapeo de hidratación en `ArticuloRepository::findById()`.
De esta forma, todo Articulo levantado desde la BD es idéntico a su schema local, preservando el código limpio en el frontend. El frontend ya estaba programado para degradar elegantemente si su contenido es `0` u nulo.

### Sobre la Exposición del Slug
Dado que el `slug` pertenece a la Entidad `Empresa` y no estrictamente a la Configuración (`EmpresaConfig`), se procedió a instanciar el `EmpresaRepository` dentro de `EmpresaConfigController`. Se empujó el registro maestro de la empresa hacia la vista `index.php` inyectando un bloque visual HTML simple estilo *read-only* alertando sobre su implicancia en la URL comercial.

## 4. Archivos Afectados
* `app/modules/Articulos/Articulo.php`
* `app/modules/Articulos/ArticuloRepository.php`
* `app/modules/EmpresaConfig/EmpresaConfigController.php`
* `app/modules/EmpresaConfig/views/index.php`

## 5. Pruebas y Riesgos Controlados
* Las asignaciones al objeto Articulo consideran si la base retorna `null` o flota. Cero fatal errors de tipado estricto.
* El Controller de EmpresaConfig fue protegido encapsulando `$empresa` en los catcheos de excepción para que un posible fallo de form submission (store) no quiebre la vista por variable indefinida.

## 6. Próximos Pasos Sugeridos
El catálogo Frontend ya es completamente robusto como vidriera Pyme. El avance indiscutido será conectar el Workflow final de Carrito hacia Terminal de Pedidos / Checkout.
