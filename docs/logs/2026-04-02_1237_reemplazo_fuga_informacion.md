# Mitigación de Fuga de Información en Errores Públicos

**Fecha y hora:** 2026-04-02 12:37
**Responsable:** Agente / Auditoría de Seguridad

## Archivos tocados
- `app/modules/Store/Controllers/CheckoutController.php`

## Patrón inseguro detectado
Se detectó un patrón de "Information Leakage" (fuga de información técnica) en el manejo de excepciones (`catch (\Exception $e)`). 
En particular, el controlador abortaba el flujo mostrando el volcado del mensaje de excepción PHP / base de datos directo a la interfaz del usuario:
```php
die("Hubo un incoveniente al procesar su orden: " . $e->getMessage());
```
Esto es crítico en entornos de producción porque permite a un atacante enviar datos malformados forzando excepciones del esquema, de librerías o de la base de datos (por ejemplo, errores de PDO exponiendo tipos numéricos y nombres de tabla/columna).

## Reemplazo aplicado
Se reemplazó la salida directa al cliente por un mensaje oculto a nivel de sistema (`error_log()`) y se estandarizó la salida visual mediante un `die()` con un mensaje genérico inofensivo:
```php
error_log("CheckoutController Exception (confirm): " . $e->getMessage());
die("Lo sentimos. Hubo un inconveniente técnico al procesar el pedido. Intentá de nuevo a la brevedad.");
```

## Por qué el cambio no rompe acceso local
- **Sesiones y Autenticación Intactas:** No se introdujeron, modificaron ni reestructuraron las cabeceras HTTP de autenticación ni componentes CSRF, manteniendo el acceso de los administradores y usuarios locales completamente inalterado.
- **Ruteo inalterado:** La petición al checkout sigue cayendo en el mismo endpoint, y el caso feliz de éxito completa el pedido web con normalidad. Solo se alteró el flujo exacto de la salida de texto (string content) en el caso excepcional ("catch").
- **Trazabilidad asegurada:** En lugar de silenciar el error completamente o emitir una excepción nula, el `error_log` asegura que el desarrollador pueda acceder al stack en su entorno local PHP (`php_errors.log` o equivalente), de manera segura y confidencial.
