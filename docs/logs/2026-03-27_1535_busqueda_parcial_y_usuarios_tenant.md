# [UX/Auth] - Busqueda parcial real y gestion de usuarios por tenant

## Que se hizo
- Se ajusto el autosuggest comun para que `Enter` con texto parcial filtre el CRUD sin obligar a elegir una sugerencia.
- Si el operador selecciona una sugerencia del dropdown, ahora se completa el input y se ejecuta el filtro inmediatamente.
- Se habilito el modulo `Administrar Cuentas` para usuarios logueados del tenant, manteniendo el aislamiento por `empresa_id`.

## Por que
- El comportamiento anterior mezclaba sugerencia con obligacion de seleccion y rompía el flujo natural de busqueda parcial.
- La gestion de usuarios del tenant debia estar disponible para usuarios operativos de la empresa, no solo para perfiles marcados como admin.

## Impacto
- Todas las busquedas basadas en `rxn-crud-search.js` quedan mas naturales y utiles para filtrar parciales.
- Los usuarios de una empresa pueden entrar a `Administrar Cuentas` y operar solo dentro de su propio contexto.

## Decisiones tomadas
- Se mantuvo el aislamiento multiempresa existente en repositorios y servicios; solo se amplio el permiso de acceso al modulo de usuarios para usuarios autenticados del tenant.
