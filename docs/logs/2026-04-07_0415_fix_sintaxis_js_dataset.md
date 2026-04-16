# 2026-04-07 04:15: Fix Definitivo — Template Literal JS Sin Cerrar (RXN Live)

## Qué se hizo
Se identificó y corrigió la causa raíz real del fallo total de JavaScript en `dataset.php`:
un template literal de JS que nunca se cerraba correctamente, matando **todo el código JS de la página**.

## Por qué / Causa Raíz
En una iteración anterior se intentó cerrar el template literal en la función `buildDiscreteDropdown()` 
con la secuencia `</div>\`;` — pero el `\`` (backslash + backtick) **dentro de un template literal 
de JavaScript significa un backtick escapado (literal)**, NO el cierre del template.

Como resultado, el template literal iniciado en la línea del `html += \`` nunca cerraba. El motor 
JS del browser interpretaba el resto del archivo completo como contenido del string, incluyendo 
todas las funciones (`renderPlana`, `DOMContentLoaded`, etc.), haciéndolas **invisibles como código**.

Consecuencia: pantalla en blanco total, 7 errores en consola, sessionStorage vacío, 
sin importar cuántos Ctrl+F5 o modo incógnito se usara.

## Dónde (Impacto)
- `app/modules/RxnLive/views/dataset.php` — Función `buildDiscreteDropdown()`, lines ~691-697.
  - Se reemplazó `</div>\`;` (backslash-backtick = backtick escapado dentro del template)
  - Por `</div>` + `</div>`;` (backtick plano = cierre real del template literal)
  - Se agregó además el `</div>` faltante para cerrar el wrapper `<div class="px-2 pb-1">` de línea 667.

## Aprendizajes
Un `\`` dentro de un template literal JS es una **secuencia de escape válida** que produce 
un backtick literal sin cerrarlo. Es un error silencioso y devastador: el parser no tira error 
en la línea donde está, sino un `Unterminated template literal` al final del archivo, 
haciendo que el diagnóstico sea muy difícil sin ver el archivo raw.

**Regla de oro:** cuando se edita HTML dentro de template literals anidados de JS dentro de PHP, 
nunca usar `\`` — usar comillas simples o dobles para los strings internos, o backtick plano para cerrar.
