# Iteración Corta: Reversión a Stock Editable (Testing Mode)

## Fecha y Tema
2026-03-24 04:25 - Habilitación transitoria de la edición manual del stock de Artículos.

## 1. Contexto y Petición
Tras haber blindado la entidad en solo lectura, se solicitó de forma explícita habilitar el guardado manual desde el Front Comercial Administrativo (`views/form.php`).
**Motivo:** Necesidad de cargar stock ficticio rápidamente para testear la validación en tiempo de ejecución del "Agregar al Carrito", evitando tener que contaminar el ERP (Tango) o simular bajadas manuales por API.

## 2. Implementación Técnica
Se deshicieron las restricciones de formulario impuestas previamente y se conectó la ruta del backend:
1. **Frontend:** El input mutó de `readonly disabled` a `number step="0.01"`, inyectando visualmente un warning anaranjado (`border-warning`) especificando que es un campo temporal y que se sobreescribirá.
2. **Controlador:** `ArticuloController::actualizar()` ahora recupera `$_POST['stock_actual']` del Request y lo formatea al objeto `$articulo`.
3. **Persistencia:** `ArticuloRepository::update()` inyecta ahora de manera explícita el parámetro `:stock_actual` alterando directamente el row sobre MySQL.

## 3. Riesgos Asumidos y Documentados
- Todo stock insertado aquí desaparecerá apenas el Cron o el botón "Sincronizar Tango" sea presionado para esa empresa.
- Este comportamiento no es un Bug sino la naturaleza del diseño Maestro-Esclavo priorizando a Tango.

## 4. Próximos Pasos
Culminar el flujo del checkout desde el "Ver Carrito" hasta la pasarela o pedido en borrador. El stock virtual permitirá armar carros infinitos a voluntad de la prueba.
