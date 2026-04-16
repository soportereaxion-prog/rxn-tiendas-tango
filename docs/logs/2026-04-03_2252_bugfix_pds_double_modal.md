# Corrección de Modales Duplicados en Formulario PDS

**Fecha**: 2026-04-03
**Módulo afecado**: PDS (Pedidos de Servicio) (`form.php`)

## Qué se hizo
1. Se depuraron scripts estáticos duplicados (`rxn-confirm-modal.js` y `rxn-shortcuts.js`) en la vista de edición/alta de PDS que ya estaban siendo inyectados globalmente por el `admin_layout.php`.

## Por qué
El motor del modal detecta cualquier botón con metadata interactiva o submisión en el formulario e inyecta un eventListener al bloque principal `document`. Al cargar en PDS el script dos veces, se ataba el disparo dos veces en la cola de memoria del JS, causando que se instancien dos modales de forma invisible. Al hacer click en "Aceptar", el DOM ocultaba el superior y automáticamente destapaba el inferior junto con una animación remanente del backdrop de Bootstrap.

## Estado
Operativo; interacciones reparadas.
No impacta en el ruteo de envíos de PDS a BD (mantienen sus IDs de HTML5 form vinculantes).
