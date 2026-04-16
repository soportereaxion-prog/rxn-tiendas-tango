# Favicon en Entorno CRM / Backoffice

## Fecha y Autor
- **Fecha:** 2026-04-04 11:45
- **Módulo:** Core / Vistas
- **Impacto:** Menor (UI/UX)

## Qué se hizo
Se modificó el archivo `app/shared/views/admin_layout.php` para integrar el icono de navegador (favicon) configurado por la empresa.

## Por qué
Para que el usuario logueado en el backoffice visualice el mismo favicon ("Ícono del Navegador") de la empresa activa que se presenta en la tienda B2C, mejorando el contexto visual operativo de las pestañas en el navegador.

## Implementación
1. Se consume el Helper `\App\Core\Context::getEmpresaId()` dentro de la cabecera del layout `admin_layout.php`.
2. Si existe la empresa, se busca por `id` en `EmpresaRepository` para recuperar el valor de `favicon_url`.
3. Se inyecta la etiqueta `<link rel="icon">` en el `<head>` del HTML.

## Decisiones tomadas
- Se aplicó manejo de excepciones para el intento de recuperación de empresa desde base de datos de manera de que un fallo en BD o una migración incompleta no quiebre la renderización global del backoffice (silent error para fallos no críticos visuales).
- Se centralizó localmente en el layout maestro ya que la persistencia y carga de esta metadata no amerita un contexto reactivo complejo en la arquitectura actual.

## Trazabilidad
- `app/shared/views/admin_layout.php` modificado.
- Versión liberada en `app/config/version.php` como `1.1.49` (build `20260404.2`) debido a tratarse de una mejora de UI de cara al cliente y sus operadores.
