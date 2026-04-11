# RXN Suite

Sistema CRM multi-tenant Re@xion Soluciones.

## Desarrollo local

### ⚠️ IMPORTANTE — Server local vs worktree git

El server PHP local (XAMPP/Laragon/similar) sirve archivos desde la **carpeta principal del proyecto** (`D:\RXNAPP\3.3\www\rxn_suite\`). **No sirve desde los git worktrees** en `.claude/worktrees/<branch>/`.

Al editar código:

- **Cambios a archivos en la carpeta principal** → visibles al recargar el browser (Ctrl+F5)
- **Cambios a archivos en un worktree** → **invisibles para el browser** hasta que se mergee a main

Si un cambio "no funciona" después de un `Ctrl+Shift+R` con cache disabled en DevTools, la **primera verificación** debe ser que el archivo editado esté en el path que el server sirve (`public/...` para frontend estático, `app/...` para backend PHP), **no en un worktree**.

Los worktrees son útiles para aislamiento git de features en paralelo, pero **no para testing en runtime** del server local.

### Antipatrón conocido

Si aparece la secuencia *"apliqué un fix → no funciona → aplico otro → sigue sin funcionar"*: **parar y verificar el path**. La causa más probable es un mismatch entre el worktree donde se está editando y la carpeta que el server sirve, no un bug lógico.
