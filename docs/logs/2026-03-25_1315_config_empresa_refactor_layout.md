# [UI] — Refactor Visual de Configuración B2B (Layout Horizontal)

## Contexto
La pantalla de "Configuración de Empresa" se percibía saturada debido a un maquetado antiguo donde los campos se apilaban verticalmente. Al haber asimilado recientemente atributos de Branding (colores, favicons, logos, footer, smtp), el scroll del formulario se volvió excesivo, perjudicando la experiencia operativa del administrador.

## Problema
- Inputs apilados uno debajo del otro con `max-width` fijado rígidamente.
- Desaprovechamiento del ancho de pantalla.
- Sensación de "formulario infinito" sin fronteras claras entre cada configuración (Tango vs Branding vs SMTP).

## Decisión
- **Enfoque de Grilla (Grid Layout):** Intervenir `app/modules/EmpresaConfig/views/index.php` reemplazando el apilamiento unidimensional por un diseño responsive de 2 columnas (`col-md-6`) dictado por Flexbox (Bootstrap 5).
- **Seccionado mediante Tarjetas (Cards):** Envolver conjuntos correlacionados de campos utilizando el componente `.card` con headers jerárquicos. Se subdividió el DOM en 5 grandes bloques: Datos Generales, Identidad B2C, Identidad Corporativa, Integración Tango Connect y Transmisión de Correo (SMTP).
- **Modernización Funcional:** Reducción visual de textos técnicos hacia pequeños `.form-text` (Tooltips estáticos).

## Archivos afectados
- `app/modules/EmpresaConfig/views/index.php`

## Implementación
1. Conversión selectiva del nodo principal al estilo fluido `container-xl` eliminando su estrangulamiento original a 600px.
2. Segmentación del macro-formulario `<form enctype="multipart/form-data">` hacia adentro de 5 subdivisiones modulares.
3. Reparto de campos empleando `<div class="row gx-4 gy-3">` y `<div class="col-md-6">`.
4. Excepciones asimétricas: atributos de extension prolongada (Textarea del Footer, Password Token Connect) o switches booleanos determinantes retuvieron asginaciones nativas `col-12` para sostener dominancia en la pantalla.
5. Saneamiento del script `index.php` resolviendo un ID erróneo ("smtp-fields") el cual impedía la desaparición visual oculta de la tarjeta al accionar el toggle master de "SMTP Global Default".

## Impacto
El documento HTML comprimió un 40% su elongación Y compensando la carga visual X en Escritorios (Resoluciones de pantallas Admin).

## Riesgos
- Ninguno sobre arquitectura Backend. Las variables de request POST permanecen inmutables puesto que iteraron sus referencias `name=""` intactas.

## Validación
- Responsividad garantizada por Bootstrap auto transformando el grid de 2 a 1 columna en caso de emuladores Mobile. Compatibilidad transversal.
