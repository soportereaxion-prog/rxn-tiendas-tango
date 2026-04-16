# Favicon Upload en CRM

## Qué se hizo
- Se agregó el campo de subida de "Favicon" en la vista de configuración del módulo CRM (`app/modules/EmpresaConfig/views/index.php`).
- Se actualizó el controlador `EmpresaConfigController` para interceptar la subida del favicon cuando el área operativa es `crm` mediante el nuevo método `persistCrmFavicon()`.

## Por qué
- Para unificar las opciones de identidad visual permitiendo a los administradores del CRM cargar el icono directamente desde la "Configuración de la Empresa" (CRM) de forma equivalente a lo que ya hacen en "Tiendas".

## Impacto
- El campo ahora aparece correctamente en el bloque "Identidad Visual CRM".
- Impacta únicamente el campo global `favicon_url` dentro de la tabla `empresas`, permitiendo que el header inyectado previamente renderice la imagen actualizada.

## Decisiones tomadas
- Se decidió aislar la persistencia del Favicon del CRM en un método separado (`persistCrmFavicon`) para no reciclar `persistStoreBranding` y así evitar sobrescrituras de `null` en propiedades de la Tienda (logo principal, colores, etc.) que no se gestionan en la vista del CRM.
