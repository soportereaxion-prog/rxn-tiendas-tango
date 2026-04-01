# Implementación de Seguridad Global (Headers, Sesiones, XSS, Prevención LFI/Auth)

**Fecha:** 2026-04-01 16:39

## Qué se hizo
- **Hardening de Sesiones:** Se agregaron settings a la sesión en `app/core/App.php` (`HttpOnly`, `SameSite = Lax`, `strict_mode = 1`, `use_only_cookies = 1`, flag seguro si hay HTTPS detectado).
- **Security Headers:** Se incorporaron HTTP headers primordiales en `public/index.php` (`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`).
- **Autocarga Helper Seguridad:** Se requirió forzosamente la carga del script `app/core/helpers.php` (donde reside `h()`) directamente desde `index.php` para poder escapar de forma segura strings contra ataques XSS en cualquier vista.
- **Sanitización Input Críticos:** Se blindo el `UsuarioService` aplicando `strip_tags()` a los nombres al registrar o actualizar usuarios y forzando la validación del formato en emails mediaten `FILTER_VALIDATE_EMAIL`.
- **Motor CSRF (Fase 1):** Se forjó `app/core/CSRF.php` con métodos básicos `getToken()`, `validate()` y `csrfField()` listos para empezar a integrarse sin romper todo el HTML de las vistas ya escritas.

## Por qué
Estos cambios conforman las recomendaciones de seguridad base mandatorias de PHP y estándares web para que el proyecto pueda ser deployado de manera responsable y asegurar que el estado quede salvaguardado de vulnerabilidades comunes (Session Fixation, Cross-Site Scripting, LFI Base, y Cross-Site Request Forgery a futuro).

## Impacto
- Sesiones con mayor grado de restricción mitigando secuestros.
- Vistas preparadas para empezar a iterar escapar todo input dinámico con \<?= h($data) ?>.
- Funciones bases completadas sin acoplamiento a frameworks externos manteniendo los lineamientos de arquitectura en `AGENTS.md`.

## Decisiones tomadas
- El token CSRF fue creado en base structure pero no validado forzosamente global todavía para evitar fallas transversales de flujos AJAX existentes. Se debe implementar gradualmente vista por vista.
- Se reutiliza la función `h()` que ya estaba codificada mitigando inyecciones XSS.
