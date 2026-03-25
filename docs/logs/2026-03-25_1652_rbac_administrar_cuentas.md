# [SEGURIDAD/UI] — Restauración Panel Usuarios e Inyección RBAC en ABM de Cuentas

## Contexto
El superusuario (Master RXN) reportó la desaparición de la tarjeta "Administrar Cuentas" dentro de su vista "Entorno Operativo". Las revisiones señalaron que los controles de acceso introducidos previamente fueron ciegamente restrictivos. Durante el análisis, se comprobó adicionalmente que la capa del backend (Controlador) carecía de validación restrictiva.

## Problemas
- **(UI) Tarjeta Inaccesible:** La lógica en `tenant_dashboard` comprobaba exclusivamente que la sesión correspondiera a un `Tenant Admin` (`$_SESSION['es_admin'] == 1`). El Superusuario viaja con la flag `$_SESSION['es_rxn_admin'] == 1`, ergo, la validación fallaba y se aplicaba el `unset()` a la tarjeta de su vista.
- **(Auth) Endpoint Vulnerable:** El archivo `UsuarioController.php` solo invocaba la verificación trivial `AuthService::requireLogin()`. Al carecer de RBAC (Role-Based Access Control) robusto en la capa del server, un empelado normal (cajero, vendedor) que descubriera o tipeara manualmente la URL `/mi-empresa/usuarios` obtenía acceso total y sin filtros para crear, editar o destruir a otros administradores.

## Implementación
1. **Recuperación Visual:** En `tenant_dashboard.php` se inyectó una operación booleana que concede renderización *tanto* a Tenant Admins (`==1`) *como* a Global Admins (`==1`).
2. **Blindaje Endpoints:** Se codeó el wrapper privado `$this->requireAdmin()` dentro del `UsuarioController`, incrustándolo como primera instrucción obligatoria dentro de `index()`, `create()`, `store()`, `edit()` y `update()`. Rechaza mediante HTTP 403 a todo individuo sin privilegios jerárquicos.

## Impacto
El Sistema Multitenancy restaura una experiencia administrativa total a los Master RXN. La arquitectura solidifica un candado crítico previniendo elevación de privilegios desde cuentas subalternas. No hay riesgos residuales previstos.
