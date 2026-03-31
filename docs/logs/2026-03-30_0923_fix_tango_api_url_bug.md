# Resolución de Bug: Búsqueda Vacía en Clasificaciones PDS (Tango Connect)
Fecha: 2026-03-30
Versión: 1.1.35

## Qué se hizo
1. Se refactorizó la limpieza y validación de la URL de conexión en el constructor de `TangoApiClient`.
2. Se reemplazaron todas las llamadas a endpoints relativos crudos (p.ej. `'Get'`, `'GetById'`) por llamadas absolutas con su prefijo raíz de API (`'/Api/Get'`, `'/Api/GetById'`).

## Por qué
El usuario experimentó que el selector de clasificaciones reportaba "Sin coincidencias en Configuracion CRM" al buscar "ASETGO", aunque en el lado del servidor de base la petición CURL arrojaba que sí existía.

El problema base fue un choque semántico de enrutamiento web con Tango Connect:
- El usuario ingresó como ruta del CRM `http://svrrxn:17001/Api/Get` para la configuración general (en lugar de `http://svrrxn:17001` o `http://svrrxn:17001/Api`).
- Ocasionalmente, métodos como `fetchRichCatalog` construían la petición concatenando `'Get'` puro, convirtiendo internamente la dirección en `http://svrrxn:17001/Api/Get/Get`.
- Tango Connect sorprendentemente devuelve código HTTP `200 OK` a peticiones `.../Api/Get/Get`, pero con un cuerpo de datos `[data]` nulo/vacío en lugar de dar error 404.
- Al fallar silenciosamente en HTTP, el programa parseaba el contenido vacío como "0 resultados validos", devolviendolos al frontend. El frontend renderizaba la UI correctamente con 0 resultados mostrando el cuadro de error visual, evadiendo alertas de caída de conexión.

## Dónde
- `app/modules/Tango/TangoApiClient.php` 
  - Corrección en el constructor (trims y substr).
  - Normalización en la inyección de path en llamadas a `$client->get`.
- `app/config/version.php`

## Impacto
Puesto que `TangoApiClient` rige también la captura de Artículos, Clientes, Precios, y Perfiles, limpiar de orígen las direcciones introducidas previene de forma permanente la falla silenciosa si un usuario escribe la URL de un proceso aleatorio en los parámetros del sistema, blindando la robustez sin añadir validación restrictiva severa en pantalla.

## Decisiones tomadas
1. Se decidió realizar pre-procesado (`preg_replace`/`substr`) de la variable `$apiUrl` antes de pasarla al cliente base HTTP. 
2. Se resolvió formalizar `ApiClient` para recibir los sufijos limpios y obligatorios como `/Api/Get`, facilitando así distinguir la raíz de la instancia del recurso.
