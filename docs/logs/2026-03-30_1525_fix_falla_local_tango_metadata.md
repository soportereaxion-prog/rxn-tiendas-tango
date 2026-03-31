# Fix "Falla Local" en Metadata de Tango Connect (EmpresaConfig)

**Fecha:** 2026-03-30
**Impacto:** Medio (Impedía resolver el listado de empresas conectadas para la configuración del CRM)

## Problema detectado

En la vista de "Configuración de Empresa" (Módulo `EmpresaConfig`), al presionar el botón "Validar Conexión", la primera llamada AJAX (`test-tango`) finalizaba exitosamente mostrando un botón verde ("✅ Configurado y Funcional"). 
Sin embargo, la segunda llamada (`tango-metadata`) fallaba visualmente reportando `❌ Falla Local` con un mensaje indicando problemas de red o errores fatales del backend.

Tras investigar y ejecutar el endpoint con métricas directas (simulando los datos desde el Controller), descubrimos que **PHP estaba respondiendo con éxito HTTP 200 y emitiendo un JSON perfectamente codificado**.

El problema real residía en la manipulación silenciosa de errores en JavaScript:
La función responsable de popular los selectores (`populateTangoSelects`) referenciaba implícitamente variables globales que el navegador mapea a los IDs del DOM (ej: `sProfile` -> `<select id="tango_perfil_pedido_id">`, `profileCodeInput`, `profileNameInput`). 
Como en la vista de *EmpresaConfig* retiramos (por decisión de arquitectura) los inputs referidos a perfiles de pedido porque operan a nivel sucursal y no nivel empresa madre, esas variables implícitas dejaron de estar definidas.

Al evaluarse `if (!sProfile)`, el motor de JavaScript disparó inmediatamente un bloqueante `ReferenceError: sProfile is not defined`. Como esta ejecución ocurría dentro de un bloque `try`, este error saltaba directamente al `catch (error)`, el cual estaba hardcodeado para mostrar el temido cartel de `Falla Local` asumiendo que el fetch de red o JSON parse inicial habían sido los culpables.

## Solución implementada

En `app/modules/EmpresaConfig/views/index.php`, se definieron explícitamente las constantes empleando `document.getElementById` al principio del bloque del DOM:

```javascript
const sProfile = document.getElementById('tango_perfil_pedido_id');
const profileCodeInput = document.getElementById('tango_perfil_pedido_codigo');
const profileNameInput = document.getElementById('tango_perfil_pedido_nombre');
```

Al utilizar `document.getElementById()`, si el elemento no existe en el DOM para la vista actual (como en el caso del CRM global), el motor javascript asigna `null` a la variable de forma segura. 
Gracias a esto, el código interior puede evaluar silenciosamente `if (!sProfile)` y detener el intento de repopulación de perfiles, continuando normalmente la ejecución para autopopular el selector de "Empresas", que sí estaba presente en el frontend.

**Estado actual:** La vista resuelve las descripciones y lista correctamente las empresas disponibles según las credenciales de Tango Connect aplicadas a la instancia Global o del Tenant activo.
