# Fix: PrintForms — Color de fondo no aplicado en correos

**Fecha:** 2026-04-03 21:09  
**Área:** PrintForms / DocumentMailerService  
**Tipo:** Bugfix

---

## Qué se hizo

Se corrigió el pipeline de renderizado del canvas de email para que el color de fondo configurado en el editor visual (y la opción "Papel sin fondo / Transparente") sean correctamente propagados hasta el HTML generado.

---

## Por qué

El editor Canvas guarda correctamente `background_color` y `transparent_bg` en `page_config_json` (DB). Sin embargo, `PrintFormRenderer::buildDocument()` **nunca emitía esos campos** en el array de salida — solo salía `background_url` y `background_opacity`. 

El template `document_render.php` esperaba `$page['background_color']` y `$page['transparent_bg']`, pero como nunca llegaban, siempre tomaba el fallback `#ffffff` hardcodeado. Adicionalmente, la regla CSS `.print-page { background-color: #ffffff !important }` anulaba cualquier estilo inline.

El resultado visible: fondo negro en Thunderbird (dark mode sobreescribía el `#ffffff` implícito) y el color configurado en el editor no tenía efecto.

---

## Impacto

- Correos de PDS y Presupuestos ahora respetan el color de fondo configurado en el canvas
- "Papel sin fondo" en email establece `#ffffff` base (protección contra dark mode de clientes de correo)
- Si el usuario configura un color distinto al blanco, ese color se aplica
- Preview y PDF (no email): la transparencia funciona correctamente como antes

---

## Decisiones técnicas

- En contexto **email**: nunca transparente (los clientes de correo no soportan transparencia real). Se usa blanco por defecto, o el color configurado si es diferente al blanco.
- En contexto **preview/PDF**: se respeta transparencia si el usuario la activó.
- El `!important` en `.print-page { background-color }` fue eliminado para que el `style` inline del `<div>` tenga precedencia.

---

## Archivos modificados

- `app/modules/PrintForms/PrintFormRenderer.php` — Agrega `background_color` y `transparent_bg` al array de retorno de `buildDocument()`
- `app/modules/PrintForms/views/document_render.php` — Quita `!important` del CSS, mejora lógica de color/transparencia para email vs preview

---

## Seguridad

- Sin impacto en autenticación ni multiempresa
- Salida escapada con `htmlspecialchars()` mantenida
- No se alteró ningún flujo de datos persistente
