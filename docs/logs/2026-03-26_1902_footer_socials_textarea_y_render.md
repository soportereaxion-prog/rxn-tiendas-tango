# Footer socials como texto multilinea

## Que se hizo
- Se reemplazo el campo `footer_socials` de `app/modules/EmpresaConfig/views/index.php` para que deje de usar un `input type="url"` y pase a un `textarea` de 2 filas manteniendo el mismo `name`.
- Se amplio el rotulo a `Redes / Links Publicos` para reflejar que ahora admite contenido libre y multilinea.
- Se actualizo `app/modules/Store/views/layout.php` para renderizar `footer_socials` como texto literal usando `nl2br(htmlspecialchars(...))` dentro del bloque `Redes`.

## Por que
- El valor persistido ya se guarda como string plano, por lo que el cambio podia resolverse desde la vista sin redisenar backend.
- La tienda publica necesitaba mostrar exactamente lo que el operador escribe en configuracion, sin forzar un unico enlace ni perder saltos de linea.

## Impacto
- La configuracion de empresa ahora permite cargar varias redes, links o texto libre en un solo campo multilinea.
- El footer publico muestra ese contenido con el mismo criterio visual multilinea que `footer_text`.

## Decisiones tomadas
- No se agrego parseo automatico, split por lineas ni iconografia adicional.
- No fue necesario actualizar `docs/estado/current.md` porque el ajuste queda correctamente trazado en este log puntual.
